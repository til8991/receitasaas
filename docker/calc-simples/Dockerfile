# syntax=docker/dockerfile:1.7
# Calculadora RTC - Simples Nacional (ReceitaFederal/calculadora-rtc-simples-nacional)
# Build context: services/calculadora-rtc-simples-nacional (git submodule, never modified).
# Additional context "regular": services/calculadora-rtc-regime-regular.
#
# The Simples repository ships no flyway/ folder. Its README says to copy a flyway/ folder
# into the project root; the tables it reads (ATIVIDADE_SIMPLES_NACIONAL,
# REGIME_APURACAO_SIMPLES_NACIONAL, VERSAO_BASE_DADO, ...) are created by the
# regime-regular migrations, so we copy flyway/ from that repository. See docs/calculadoras.md.

ARG JDK_IMAGE=eclipse-temurin:21-jdk
ARG JRE_IMAGE=eclipse-temurin:21-jre

# ---------------------------------------------------------------- 1. build JAR
FROM ${JDK_IMAGE} AS build
WORKDIR /src
COPY .mvn .mvn
COPY mvnw pom.xml ./
RUN sed -i 's/\r$//' mvnw && chmod +x mvnw
COPY src src
RUN --mount=type=cache,id=receitasass-m2,target=/root/.m2,sharing=locked \
    ./mvnw -B -q package -DskipTests \
 && cp target/api-simples-nacional.jar /app.jar

# ------------------------------------------------- 2. generate SQLite database
FROM build AS db
COPY --from=regular flyway flyway
RUN --mount=type=cache,id=receitasass-m2,target=/root/.m2,sharing=locked \
    mkdir -p ./calculadora/db \
 && ./mvnw -B flyway:migrate -Dflyway.configFiles=./flyway/flyway-pro.conf --batch-mode --errors \
 && test -s ./calculadora/db/calculadora-pro.db

# ------------------------------------------------------------------ 3. runtime
FROM ${JRE_IMAGE} AS runtime
RUN apt-get update \
 && apt-get install -y --no-install-recommends curl \
 && rm -rf /var/lib/apt/lists/* \
 && useradd --system --uid 10001 --home /app calc
WORKDIR /app
COPY --from=db /src/calculadora/db/calculadora-pro.db ./calculadora/db/calculadora-pro.db
COPY --from=build /app.jar ./app.jar
RUN chown -R calc:calc /app
USER calc

ENV SPRING_PROFILES_ACTIVE=offline \
    JAVA_TOOL_OPTIONS="-XX:MaxRAMPercentage=75"
EXPOSE 8082

HEALTHCHECK --interval=15s --timeout=5s --start-period=90s --retries=5 \
  CMD curl -fsS http://localhost:8082/api/versao || exit 1

ENTRYPOINT ["java", "-jar", "/app/app.jar"]
