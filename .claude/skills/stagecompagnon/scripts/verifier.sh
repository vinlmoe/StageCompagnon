#!/usr/bin/env bash
# Contrôles locaux rapides avant une poussée, sans installation Moodle : syntaxe PHP des
# fichiers modifiés, parité des chaînes de langue en/fr, cohérence version.php / upgrade.php /
# install.xml / CHANGELOG.md, puis phpcs si le standard Moodle est installé.
# Usage : verifier.sh [référence de base, par défaut origin/main]
set -u
cd "$(git rev-parse --show-toplevel)" || exit 2
base="${1:-origin/main}"
statut=0
echec() { echo "ÉCHEC : $*"; statut=1; }

# Fichiers modifiés par rapport à la base (commités ou non), hors bibliothèque tierce.
modifies=$( { git diff --name-only "$base"...HEAD 2>/dev/null; git diff --name-only HEAD; \
    git ls-files --others --exclude-standard; } | sort -u | grep -v '/thirdparty/' )

echo "== Syntaxe PHP"
for f in $(echo "$modifies" | grep '\.php$'); do
    [ -f "$f" ] && { php -l "$f" >/dev/null 2>&1 || echec "php -l $f"; }
done

echo "== Chaînes de langue en/fr"
for m in stage stagesynthesis; do
    cles() { grep -o "^\$string\['[^']*'\]" "mod/$m/lang/$1/$m.php" | sort; }
    d=$(diff <(cles en) <(cles fr))
    [ -z "$d" ] || echec "mod_$m : clés différentes entre en et fr :"$'\n'"$d"
done

echo "== Versions"
for m in stage stagesynthesis; do
    if echo "$modifies" | grep -q "^mod/$m/db/\(install.xml\|upgrade.php\)$"; then
        git diff --quiet "$base"...HEAD -- "mod/$m/version.php" 2>/dev/null \
            && git diff --quiet HEAD -- "mod/$m/version.php" \
            && echec "mod_$m : schéma modifié sans changement de version.php"
    fi
    if [ -f "mod/$m/db/upgrade.php" ]; then
        v=$(grep -o 'version *= *[0-9]*' "mod/$m/version.php" | grep -o '[0-9]*$')
        sp=$(grep -o "upgrade_mod_savepoint(true, [0-9]*" "mod/$m/db/upgrade.php" | tail -1 | grep -o '[0-9]*$')
        [ "$sp" -le "$v" ] || echec "mod_$m : point de sauvegarde $sp > version $v"
        x=$(grep -o 'VERSION="[0-9]*"' "mod/$m/db/install.xml" | grep -o '[0-9]*')
        [ "$x" = "${sp:0:8}" ] || echo "Attention : mod_$m install.xml VERSION=$x, dernier point de sauvegarde $sp"
    fi
done
if echo "$modifies" | grep -q 'mod/.*/version.php' && ! echo "$modifies" | grep -q '^CHANGELOG.md$'; then
    echec "version.php modifié sans entrée dans CHANGELOG.md"
fi

echo "== Style Moodle (phpcs)"
phpcs=""
for c in phpcs /tmp/cs/vendor/bin/phpcs "$(composer global config bin-dir --absolute 2>/dev/null)/phpcs"; do
    command -v "$c" >/dev/null 2>&1 && { phpcs="$c"; break; }
done
if [ -n "$phpcs" ]; then
    cibles=$(echo "$modifies" | grep '^mod/.*\.php$' | while read -r f; do [ -f "$f" ] && echo "$f"; done)
    [ -z "$cibles" ] || "$phpcs" --standard=phpcs.xml -q $cibles || echec "phpcs"
else
    echo "phpcs absent : voir « Contrôle continu » dans README.md pour l'installer."
fi

[ $statut -eq 0 ] && echo "Tous les contrôles locaux passent." || echo "Des contrôles échouent."
exit $statut
