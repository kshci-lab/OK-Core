#!/bin/sh
set -eu

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

BASE_URL="${BASE_URL:-http://localhost:8888/OK-Core}"
IDP_URL="${HCIMLAB_SSO_IDP_URL:-https://kshci-lab.net/software/hcimlab_auth}"
CLIENT_ID="${HCIMLAB_SSO_CLIENT_ID:-your-ok-core-client-id}"
CLIENT_SECRET="${HCIMLAB_SSO_CLIENT_SECRET:-your-ok-core-client-secret}"
REDIRECT_URI="${BASE_URL%/}/auth/callback"
SSO_LOCAL_PATH="$ROOT_DIR/php/sso_local.php"
CERTS_DIR="$ROOT_DIR/certs"
CA_BUNDLE_PATH="$CERTS_DIR/cacert.pem"
COMPOSER_HOME="${COMPOSER_HOME:-$ROOT_DIR/.composer}"
export COMPOSER_HOME

resolve_php_bin() {
  if [ -n "${PHP_BIN:-}" ]; then
    printf '%s\n' "$PHP_BIN"
  elif [ -x "/Applications/MAMP/bin/php/php8.3.14/bin/php" ]; then
    printf '%s\n' "/Applications/MAMP/bin/php/php8.3.14/bin/php"
  elif [ -x "/Applications/MAMP/bin/php/php8.3.1/bin/php" ]; then
    printf '%s\n' "/Applications/MAMP/bin/php/php8.3.1/bin/php"
  elif command -v php >/dev/null 2>&1; then
    command -v php
  else
    echo "PHP was not found. Set PHP_BIN and rerun this script." >&2
    exit 1
  fi
}

ensure_composer_phar() {
  if [ -f "$ROOT_DIR/composer.phar" ]; then
    printf '%s\n' "$ROOT_DIR/composer.phar"
    return
  fi

  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "https://getcomposer.org/installer" -o "$ROOT_DIR/composer-setup.php"
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$ROOT_DIR/composer-setup.php" "https://getcomposer.org/installer"
  else
    echo "curl or wget was not found. Set COMPOSER_BIN or install Composer manually." >&2
    exit 1
  fi

  "$PHP_BIN_RESOLVED" "$ROOT_DIR/composer-setup.php" --install-dir="$ROOT_DIR" --filename=composer.phar
  rm -f "$ROOT_DIR/composer-setup.php"

  if [ ! -f "$ROOT_DIR/composer.phar" ]; then
    echo "Composer download failed. Set COMPOSER_BIN and rerun this script." >&2
    exit 1
  fi
  printf '%s\n' "$ROOT_DIR/composer.phar"
}

run_composer_install() {
  if [ "${SKIP_COMPOSER_INSTALL:-0}" = "1" ]; then
    return
  fi

  mkdir -p "$COMPOSER_HOME"

  if [ -n "${COMPOSER_BIN:-}" ]; then
    case "$COMPOSER_BIN" in
      *.phar) "$PHP_BIN_RESOLVED" "$COMPOSER_BIN" install ;;
      *) "$COMPOSER_BIN" install ;;
    esac
  elif [ -f "$ROOT_DIR/composer.phar" ]; then
    "$PHP_BIN_RESOLVED" "$ROOT_DIR/composer.phar" install
  elif command -v composer >/dev/null 2>&1; then
    composer install
  else
    COMPOSER_PHAR="$(ensure_composer_phar)"
    "$PHP_BIN_RESOLVED" "$COMPOSER_PHAR" install
  fi

  if [ ! -f "$ROOT_DIR/vendor/autoload.php" ]; then
    echo "Composer install did not create vendor/autoload.php." >&2
    exit 1
  fi
}

ensure_ca_bundle() {
  mkdir -p "$CERTS_DIR"
  if [ -f "$CA_BUNDLE_PATH" ]; then
    return
  fi

  if command -v curl >/dev/null 2>&1; then
    curl -fsSL "https://curl.se/ca/cacert.pem" -o "$CA_BUNDLE_PATH" || true
  elif command -v wget >/dev/null 2>&1; then
    wget -qO "$CA_BUNDLE_PATH" "https://curl.se/ca/cacert.pem" || true
  fi

  if [ ! -f "$CA_BUNDLE_PATH" ]; then
    echo "Could not download certs/cacert.pem. If SSO shows cURL error 60, download it manually from https://curl.se/ca/cacert.pem." >&2
  fi
}

write_sso_local() {
  if [ -f "$SSO_LOCAL_PATH" ] && [ "${FORCE:-0}" != "1" ]; then
    echo "php/sso_local.php already exists. Set FORCE=1 to recreate it."
    return
  fi

  cat > "$SSO_LOCAL_PATH" <<PHP
<?php

return array(
    'idp_url' => '$IDP_URL',
    'client_id' => '$CLIENT_ID',
    'client_secret' => '$CLIENT_SECRET',
    'base_url' => '${BASE_URL%/}',
    'redirect_uri' => '$REDIRECT_URI',
    'scope' => 'openid profile email lab',
);

PHP

  echo "Created php/sso_local.php."
  echo "Register this redirect URI in HCIMLab SSO: $REDIRECT_URI"
}

PHP_BIN_RESOLVED="$(resolve_php_bin)"
run_composer_install
ensure_ca_bundle
write_sso_local

echo "OK-Core local setup complete."
