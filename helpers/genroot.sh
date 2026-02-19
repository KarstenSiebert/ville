#!/bin/bash

set -euo pipefail

MNEMONIC_FILE="mnemonic.txt"

ROOT_XSK="root.key" 

cardano-address recovery-phrase generate --size 24 > "$MNEMONIC_FILE"

# cat "$MNEMONIC_FILE" | cardano-address key from-recovery-phrase Shelley > "$ROOT_XSK"

cardano-address key from-recovery-phrase Shelley < "$MNEMONIC_FILE" | openssl base64 -A > "$ROOT_XSK"

# echo "Mnemonic saved to $MNEMONIC_FILE"

# echo "Base64-encoded root key saved to $ROOT_XSK"

exit 0
