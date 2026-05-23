# Security

> **Language**: English · [Русский](SECURITY.ru.md)

This document describes the security posture of **pet-symphony**, what's enforced, what's pending, and how to report vulnerabilities.

## Reporting a vulnerability

**Do not** open a public GitHub Issue for security problems.

- Email: **enrinkopiece@gmail.com**
- Response SLA: initial acknowledgement within **2 business days**, triage within **5 business days**.

Please include: a description of the issue, steps to reproduce, version/commit affected, your assessment of impact. We follow [coordinated disclosure](https://en.wikipedia.org/wiki/Coordinated_disclosure) — please give us a reasonable window to ship a fix before public disclosure.

## Threat model (in scope)

- Compromise of a running container (RCE in the app, dependency CVE).
- Leak of credentials via logs, env, or source control.
- Supply-chain attack on a dependency or base image.
- Privilege escalation from the container to the host or other pods.
- Unauthorized data access (DB credentials, application data).

Out of scope: DDoS against the public ingress; social engineering of maintainers.

## Enforced baseline

| # | Control | Where | Status |
| --- | --- | --- | --- |
| 1 | Container non-root (UID 10001) | `Dockerfile` → K8s `runAsNonRoot: true` | ✅ |
| 2 | Base images pinned by tag (no `:latest`) | `Dockerfile`, `compose.override.yaml` | ✅ |
| 3 | Lockfile committed | `composer.lock`, `package-lock.json` | ✅ |
| 4 | `.env` excluded from git and Docker context | `.gitignore`, `.dockerignore` | ✅ |
| 5 | Healthcheck endpoint (`/healthz`, `/readyz`) | `HealthController` + K8s probes | ✅ |
| 6 | K8s `readOnlyRootFilesystem: true` | `kustomize/base/deployment.yaml` | ✅ (emptyDir mounts cover all write paths) |
| 7 | K8s drops all Linux capabilities | `kustomize/base/deployment.yaml` | ✅ |
| 8 | K8s `seccompProfile: RuntimeDefault` | `kustomize/base/deployment.yaml` | ✅ |
| 9 | K8s explicit `resources.requests` + `limits` | `kustomize/base/deployment.yaml` + overlays | ✅ |
| 10 | K8s default-deny `NetworkPolicy` + allow rules | `kustomize/base/networkpolicy-*.yaml` | ✅ |
| 11 | CI dependency vuln scan (Trivy) | `.github/workflows/ci.yaml` `security` job | ✅ |
| 12 | CI secret detection (gitleaks) | `.github/workflows/ci.yaml` `security` job | ✅ |

## Overrides and known gaps

### K8s Secrets are base64-encoded, not encrypted at rest

`pet-symphony-secrets` (referenced in `deployment.yaml`) must be created manually per environment. K8s Secrets are base64 only — not encrypted unless the cluster has KMS configured.

**Recommended upgrade**: external-secrets-operator pulling from AWS Secrets Manager / HashiCorp Vault.

```bash
# Local K8s (dev/test only):
kubectl create secret generic pet-symphony-secrets \
  --from-literal=APP_SECRET=<value> \
  --from-literal=DATABASE_URL=postgresql://app:pass@postgres:5432/app \
  -n pet-symphony-dev
```

### Semgrep is non-blocking until token is set

CI runs semgrep with `continue-on-error: true`. To enable blocking:
1. Add `SEMGREP_APP_TOKEN` in GitHub → Settings → Secrets and variables → Actions.
2. Remove `continue-on-error: true` from the `semgrep` step in `.github/workflows/ci.yaml`.

## Recommended but not implemented

- [ ] Image signing with cosign (keyless OIDC via GitHub Actions OIDC → Fulcio → Rekor)
- [ ] SBOM generation per release (syft, attached to release artifacts)
- [ ] Secrets via external-secrets-operator instead of plain K8s Secret
- [ ] Dependabot or Renovate for automated dependency updates
- [ ] Signed commits enforced via branch protection
- [ ] `cert-manager` with Let's Encrypt for K8s TLS (currently `secretName` placeholders)

## Secrets handling

| Layer | Mechanism |
| --- | --- |
| Local dev | `.env.local` (gitignored), loaded by `docker-compose` |
| CI | GitHub Actions Secrets (`SEMGREP_APP_TOKEN`) |
| Container runtime | Environment variables injected by orchestrator via ConfigMap + Secret |
| Production K8s | Plain K8s Secret (target: external-secrets-operator) |

**Never** commit a populated `.env`, paste secrets in PR descriptions, log them, or pass them as `--build-arg`.

## Logging policy

- Logs are written to stdout (captured by the cluster log pipeline or `docker compose logs`).
- Known secret-bearing keys (`password`, `token`, `secret`, `authorization`, `cookie`) should be redacted at the logger layer — add Monolog `SecretRedactor` processor when logging is wired up.
- Request/response bodies should not be logged in full.

## Acknowledgments

We thank the following reporters for responsibly disclosing vulnerabilities:

- *(none yet)*
