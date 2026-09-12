FROM public.ecr.aws/docker/library/alpine:3.20

ARG MINIO_VERSION=2025-09-07T16-13-09Z
ARG MC_VERSION=2025-08-13T08-35-41Z

RUN apk add --no-cache ca-certificates curl \
    && curl -fsSL "https://dl.min.io/server/minio/release/linux-amd64/archive/minio.RELEASE.${MINIO_VERSION}" -o /usr/local/bin/minio \
    && curl -fsSL "https://dl.min.io/client/mc/release/linux-amd64/archive/mc.RELEASE.${MC_VERSION}" -o /usr/local/bin/mc \
    && chmod +x /usr/local/bin/minio /usr/local/bin/mc \
    && apk del curl

ENTRYPOINT ["minio"]
