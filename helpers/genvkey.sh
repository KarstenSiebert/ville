#!/bin/bash

set -euo pipefail

cardano-address key child 1852H/1815H/0H/0/$2 < $1 > $3key.xsk

cardano-cli key convert-cardano-address-key --shelley-payment-key --signing-key-file $3/key.xsk --out-file $3payment.skey

cardano-cli key verification-key --signing-key-file $3payment.skey --verification-key-file $3payment.vkey

exit 0
