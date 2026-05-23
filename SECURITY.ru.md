# Безопасность

> **Язык**: Русский · [English](SECURITY.md)

Этот документ описывает контракт безопасности **pet-symphony**: что enforced, что ожидает реализации, и как сообщать об уязвимостях.

## Как сообщить об уязвимости

**Не** открывайте публичный GitHub Issue для security-проблем.

- Email: **enrinkopiece@gmail.com**
- SLA реакции: подтверждение в течение **2 рабочих дней**, триаж — **5 рабочих дней**.

Пожалуйста, укажите: описание проблемы, шаги воспроизведения, версию/коммит, оценку воздействия. Используем [coordinated disclosure](https://en.wikipedia.org/wiki/Coordinated_disclosure).

## Модель угроз (в скоупе)

- Компрометация работающего контейнера (RCE в приложении, CVE в зависимости).
- Утечка credentials через логи, env-переменные или систему контроля версий.
- Supply-chain атака на зависимость или базовый образ.
- Escape привилегий из контейнера на хост или другие поды.
- Несанкционированный доступ к данным (credentials БД, данные приложения).

Не в скоупе: DDoS против публичного ingress; социальная инженерия мейнтейнеров.

## Enforced baseline

| # | Контроль | Где | Статус |
| --- | --- | --- | --- |
| 1 | Контейнер под non-root (UID 10001) | `Dockerfile` → K8s `runAsNonRoot: true` | ✅ |
| 2 | Базовый образ запиннен по тегу (без `:latest`) | `Dockerfile`, `compose.override.yaml` | ✅ |
| 3 | Lockfile закоммичен | `composer.lock`, `package-lock.json` | ✅ |
| 4 | `.env` исключён из git и Docker-контекста | `.gitignore`, `.dockerignore` | ✅ |
| 5 | Healthcheck-эндпоинты (`/healthz`, `/readyz`) | `HealthController` + K8s probes | ✅ |
| 6 | K8s `readOnlyRootFilesystem: true` | `kustomize/base/deployment.yaml` | ✅ (emptyDir покрывает все пути записи) |
| 7 | K8s — все Linux capabilities сброшены | `kustomize/base/deployment.yaml` | ✅ |
| 8 | K8s `seccompProfile: RuntimeDefault` | `kustomize/base/deployment.yaml` | ✅ |
| 9 | K8s явные `resources.requests` + `limits` | `kustomize/base/deployment.yaml` + overlays | ✅ |
| 10 | K8s default-deny `NetworkPolicy` + allow rules | `kustomize/base/networkpolicy-*.yaml` | ✅ |
| 11 | CI скан зависимостей на CVE (Trivy) | `.github/workflows/ci.yaml` job `security` | ✅ |
| 12 | CI поиск секретов в коде (gitleaks) | `.github/workflows/ci.yaml` job `security` | ✅ |

## Overrides и известные пробелы

### K8s Secrets закодированы base64, не зашифрованы

`pet-symphony-secrets` (на который ссылается `deployment.yaml`) нужно создавать вручную на каждый environment. K8s Secrets — только base64, не зашифрованы, если в кластере не настроен KMS.

**Рекомендуемый апгрейд**: external-secrets-operator с AWS Secrets Manager / HashiCorp Vault.

```bash
# Для локального K8s (только dev/test):
kubectl create secret generic pet-symphony-secrets \
  --from-literal=APP_SECRET=<значение> \
  --from-literal=DATABASE_URL=postgresql://app:pass@postgres:5432/app \
  -n pet-symphony-dev
```

### Semgrep не блокирует CI, пока не добавлен токен

CI запускает semgrep с `continue-on-error: true`. Чтобы включить блокировку:
1. Добавить `SEMGREP_APP_TOKEN` в GitHub → Settings → Secrets and variables → Actions.
2. Убрать `continue-on-error: true` из шага `semgrep` в `.github/workflows/ci.yaml`.

## Рекомендуется (пока не реализовано)

- [ ] Подпись образов через cosign (keyless OIDC: GitHub Actions → Fulcio → Rekor)
- [ ] Генерация SBOM на каждый релиз (syft, прикреплять к release)
- [ ] Секреты через external-secrets-operator вместо чистого K8s Secret
- [ ] Dependabot или Renovate для автоматических обновлений зависимостей
- [ ] Подписанные коммиты enforced через branch protection
- [ ] `cert-manager` с Let's Encrypt для K8s TLS (сейчас — placeholder `secretName`)

## Обращение с секретами

| Слой | Механизм |
| --- | --- |
| Локальная разработка | `.env.local` (gitignored), читается `docker-compose` |
| CI | GitHub Actions Secrets (`SEMGREP_APP_TOKEN`) |
| Контейнерный рантайм | Переменные окружения через ConfigMap + Secret |
| Production K8s | Plain K8s Secret (цель: external-secrets-operator) |

**Никогда** не коммитьте заполненный `.env`, не вставляйте секреты в PR, не логируйте их, не передавайте через `--build-arg`.

## Политика логирования

- Логи пишутся в stdout (перехватываются log pipeline кластера или `docker compose logs`).
- Известные ключи с секретами (`password`, `token`, `secret`, `authorization`, `cookie`) должны редактироваться на уровне логгера — добавить `SecretRedactor` процессор Monolog при настройке логирования.
- Тела запросов/ответов не логируются целиком.

## Благодарности

Благодарим за ответственное раскрытие уязвимостей:

- *(пока никого)*
