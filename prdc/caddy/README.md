# mTLS setup

Caddy requires `ca.crt` (the CA that signs facility client certificates)
to live in this directory. In production the CA is an HSM-backed key
owned by P-RDC operations; in dev you can generate one with OpenSSL.

## Generate a dev CA and one client cert

```bash
cd caddy/

# 1. Root CA (10y, self-signed)
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
    -keyout ca.key -out ca.crt \
    -subj "/CN=P-RDC Dev CA/O=P-RDC/C=DE"

# 2. Per-facility client cert (valid 1 year)
#    Subject CN must match the facility_id used in submitted CSVs.
FACILITY=F008
openssl req -newkey rsa:4096 -nodes \
    -keyout "${FACILITY}.key" -out "${FACILITY}.csr" \
    -subj "/CN=${FACILITY}/O=Test Facility/C=DE"

openssl x509 -req -in "${FACILITY}.csr" -CA ca.crt -CAkey ca.key \
    -CAcreateserial -days 365 -sha256 \
    -out "${FACILITY}.crt"

# Facility now uses F008.crt + F008.key to authenticate:
curl --cert F008.crt --key F008.key --cacert ca.crt \
     -H "Authorization: Bearer $JWT" \
     -F "batch=@episode.csv" \
     https://prdc.example.org/batches
```

## Revoking a cert

Re-issue from a hot CA and publish a new CRL, or rebuild the trust pool
without the old cert and reload Caddy. For a production setup, use the
CA's OCSP responder and configure Caddy's `ocsp` directive.
