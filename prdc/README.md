# P-RDC MDS-PT ingest stack

One-way pipeline for MDS-PT treatment-episode batches. Submitting facilities
POST a CSV over mTLS; API Platform accepts it, a background worker runs it
through six validation gates, and accepted rows land in Postgres.
Quarantined rows return only as metadata — no patient payload ever leaves.

## Services

| Service | What it does                                                    |
|---------|-----------------------------------------------------------------|
| `proxy` | Caddy reverse-proxy; terminates TLS, verifies client certs      |
| `api`   | API Platform (Symfony); accepts batches, exposes read endpoints |
| `worker`| Same image as `api`, runs `messenger:consume` for validation    |
| `db`    | Postgres 17 with the MDS-PT schema pre-loaded from `db/init/`   |

The `internal` network is marked `internal: true` so containers on it
cannot reach the internet. Only `proxy` binds a host port.

## Repository layout

```
prdc/
├── docker-compose.yml
├── .env.example
├── db/init/
│   └── 01-schema.sql             # generated from codebooks
├── caddy/
│   ├── Caddyfile                 # mTLS config
│   └── README.md                 # how to mint CA + client certs
├── secrets/                      # gitignored
│   └── db_password.txt
└── api/
    ├── Dockerfile                # multi-stage, prod image for api + worker
    ├── composer.json
    ├── public/index.php
    ├── config/
    │   ├── bundles.php
    │   ├── routes.yaml
    │   ├── services.yaml
    │   ├── packages/
    │   │   ├── framework.yaml
    │   │   ├── doctrine.yaml
    │   │   ├── security.yaml
    │   │   ├── api_platform.yaml
    │   │   └── lexik_jwt_authentication.yaml
    │   └── jwt/                  # keypair goes here (gitignored)
    └── src/
        ├── Kernel.php
        ├── Controller/
        │   ├── BatchSubmitController.php   # POST /batches
        │   └── BatchQueryController.php    # GET  /batches/{id}/status|errors
        ├── Entity/
        │   ├── BatchLog.php
        │   ├── Quarantine.php
        │   ├── Facility.php
        │   ├── Therapist.php
        │   ├── PatientAdult.php
        │   ├── Treatment.php
        │   ├── Assessment.php
        │   └── ProcessRating.php
        ├── Message/ValidateBatch.php
        ├── MessageHandler/ValidateBatchHandler.php
        └── Validation/
            ├── ValidationPipeline.php
            └── Gate/
                ├── ValidationGateInterface.php
                ├── GateFailure.php
                ├── PseudonymFormatGate.php   # gate 0
                ├── SchemaGate.php            # gate 1
                ├── CategoricalGate.php       # gate 2 — reads Postgres ENUM/CHECK
                ├── RangeGate.php             # gate 3
                ├── IntraBatchGate.php        # gate 4
                └── UniquenessGate.php        # gate 5 — only gate that queries DB
```

## Bringing the stack up for the first time

```bash
# 1. Copy example env, pick a domain
cp .env.example .env

# 2. Generate database password
echo "$(openssl rand -hex 32)" > secrets/db_password.txt

# 3. Generate the JWT keypair (passphrase must match JWT_PASSPHRASE in .env)
openssl genrsa -out api/config/jwt/private.pem -aes256 4096
openssl rsa -in api/config/jwt/private.pem -pubout \
            -out api/config/jwt/public.pem

# 4. Generate the dev CA and one facility cert (see caddy/README.md)
cd caddy && openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
    -keyout ca.key -out ca.crt -subj "/CN=P-RDC Dev CA"
cd ..

# 5. Build & boot
docker compose build
docker compose up -d db            # loads db/init/01-schema.sql on first boot
docker compose logs -f db          # wait for "database system is ready"
docker compose up -d api worker proxy
```

## Submitting a batch

```bash
curl --cert caddy/F008.crt --key caddy/F008.key --cacert caddy/ca.crt \
     -H "Authorization: Bearer $JWT" \
     -F "batch=@episode.csv" \
     https://prdc.example.org/batches
```

Response:

```json
{"batch_id":"018f5d3e-7bef-7d12-a831-16e2b6e78c9f","status":"received"}
```

## Polling for completion

```bash
BATCH=018f5d3e-7bef-7d12-a831-16e2b6e78c9f

curl --cert caddy/F008.crt --key caddy/F008.key --cacert caddy/ca.crt \
     -H "Authorization: Bearer $JWT" \
     https://prdc.example.org/batches/$BATCH/status
```

Observable states:

| status               | meaning                                                       |
|----------------------|---------------------------------------------------------------|
| `received`           | on disk, queued for validation                                |
| `validating`         | worker has picked up the job                                  |
| `completed`          | all rows written                                              |
| `partial_quarantine` | some rows written, some rejected — see `errors_url`           |
| `blocked_for_review` | gate 5 collision — human review required, no auto-resubmit    |
| `failed`             | pipeline crashed                                              |

## Adding a gate

1. Create a class in `api/src/Validation/Gate/` that implements `ValidationGateInterface`.
2. That's it — `services.yaml` tags it via `_instanceof` and the pipeline sorts by `number()`.

## Regenerating the schema

When the codebook changes:

```bash
python3 tools/generate_schema.py \
    --adult    MDS_Adult_codebook.json \
    --pt       MDS_PT_codebook.json \
    --mapping  tools/mapping.json \
    --out      db/init/01-schema.sql
```

Then rebuild the database container for a fresh environment, or write a
Doctrine migration for a non-destructive update in production.
