#!/bin/bash

set -euo pipefail

cardano-address key child 1852H/1815H/0H/0/$2 < $1 > $3key.xsk

cardano-cli key convert-cardano-address-key --shelley-payment-key --signing-key-file $3key.xsk --out-file $3payment.skey

exit 0
