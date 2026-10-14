# mTLS setup

Caddy requires `ca.crt` (the CA that signs the DTS client certificate)
to live in this directory. In production the CA is an HSM-backed key
owned by P-RDC operations; in dev you can generate one with OpenSSL.

## Generate a dev CA and the DTS client cert

```bash
cd caddy/

# 1. Root CA (10y, self-signed)
openssl req -x509 -newkey rsa:4096 -sha256 -days 3650 -nodes \
    -keyout ca.key -out ca.crt \
    -subj "/CN=P-RDC Dev CA/O=P-RDC/C=DE"

# 2. DTS client cert (valid 1 year)
#    The Datentreuhandstelle is the only submitter, so only one client
#    cert is needed. Batches it submits may contain rows for many
#    facilities; the CN is not matched against the rows' facility_id.
#    (The dev cert is named F008 for historical reasons.)
FACILITY=F008
openssl req -newkey rsa:4096 -nodes \
    -keyout "${FACILITY}.key" -out "${FACILITY}.csr" \
    -subj "/CN=${FACILITY}/O=Datentreuhandstelle (dev)/C=DE"

openssl x509 -req -in "${FACILITY}.csr" -CA ca.crt -CAkey ca.key \
    -CAcreateserial -days 365 -sha256 \
    -out "${FACILITY}.crt"

# The DTS now uses F008.crt + F008.key to authenticate:
curl --cert F008.crt --key F008.key --cacert ca.crt \
     -H "Authorization: Bearer $JWT" \
     -F "batch=@episode.csv;type=text/csv" \
     https://localhost/batches
```

## Revoking a cert

Re-issue from a hot CA and publish a new CRL, or rebuild the trust pool
without the old cert and reload Caddy. For a production setup, use the
CA's OCSP responder and configure Caddy's `ocsp` directive.
