FROM alpine:3.20
RUN apk add --no-cache curl jq
WORKDIR /smoke
ENTRYPOINT ["sh", "/smoke/smoke-test.sh"]
