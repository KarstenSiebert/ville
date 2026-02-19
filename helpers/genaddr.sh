#!/bin/bash

set -euo pipefail

cardano-address key child 1852H/1815H/0H/0/$2 < $1 | cardano-address key public --with-chain-code > $3addr.xvk

cardano-address address payment --network-tag mainnet < $3addr.xvk > $3user.address

exit 0
