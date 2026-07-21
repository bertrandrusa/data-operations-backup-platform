FROM postgres:16-alpine

RUN apk add --no-cache bash coreutils findutils rsync shadow tzdata

COPY worker/ /opt/dataops/
COPY pipeline/ /opt/pipeline/

RUN chmod +x /opt/dataops/*.sh /opt/pipeline/*.sh \
    && addgroup -g 10001 dataops \
    && adduser -D -u 10001 -G dataops dataops \
    && mkdir -p /data/backups /data/source \
    && chown -R dataops:dataops /data/backups /data/source

USER dataops
WORKDIR /opt/dataops
