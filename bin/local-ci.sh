#!/usr/bin/env bash
# Local CI: the checks a release needs, in one run. Exits non-zero on the first failing group.
# PHPStan needs a local phpstan.neon (git-ignored, see phpstan.neon.dist); set PHPSTAN to its binary.
set -uo pipefail
cd "$(dirname "$0")/.."
SKILL=~/.claude/skills/wp-plugin-development/scripts
fail=0
step() { printf '\n== %s\n' "$1"; }

step "php -l (8.1 syntax floor is checked by phpcs PHPCompatibility)"
find . -name '*.php' -not -path './vendor/*' -not -path './dist/*' -print0 | xargs -0 -n1 php -l 2>/dev/null | grep -v '^No syntax errors' && fail=1

step "phpcs"
phpcs --standard=phpcs.xml.dist -q || fail=1

step "phpstan"
PHPSTAN=${PHPSTAN:-$(command -v phpstan || echo ~/code/wbcom-credits-sdk-verify/vendor/bin/phpstan)}
if [ -f phpstan.neon ] && [ -x "$PHPSTAN" ]; then
	"$PHPSTAN" analyse -c phpstan.neon --no-progress --memory-limit=3G || fail=1
else
	echo "phpstan.neon or phpstan binary missing (see phpstan.neon.dist)"; fail=1
fi

step "release gates"
bash "$SKILL/gate-check.sh" . || fail=1

exit $fail
