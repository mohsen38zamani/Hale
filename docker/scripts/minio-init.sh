#!/bin/sh
set -eu

echo "Waiting for MinIO..."
until mc alias set hale http://minio:9000 "${MINIO_ROOT_USER}" "${MINIO_ROOT_PASSWORD}" >/dev/null 2>&1; do
  sleep 2
done

mc mb --ignore-existing "hale/${AWS_BUCKET:-hale}"
mc anonymous set download "hale/${AWS_BUCKET:-hale}/public" 2>/dev/null || true

echo "MinIO bucket '${AWS_BUCKET:-hale}' is ready."
