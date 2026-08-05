# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et ce projet respecte [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.2.0] - 2026-08-05

### Ajouté
- Option `--domain=<name>` : génère toutes les couches sous `app/Domain/{name}/` avec les namespaces correspondants (`App\Domain\{name}\Models`, etc.) — support DDD natif.
- Option `--namespace=<name>` : remplace le namespace racine `App` par la valeur fournie dans tous les fichiers générés. Compatible avec `--domain`.
- Variable de stub `{{ modelNamespace }}` : remplace les imports de Model codés en dur par un placeholder dynamique dans tous les stubs (repository, policy, etc.).
- Variable de stub `{{ domainNamespace }}` : namespace racine de toutes les couches du run (ex: `App\Domain\Blog`), utilisée dans controller et service stubs.
- Tests unitaires : `StubReplacerTest` (6 cas), `FileGeneratorTest` (5 cas) — couverture des options domain/namespace.
- Support Laravel 13 en CI (testbench `^11.0`), avec exclusion PHP 8.2 × Laravel 13 (incompatibles).

### Modifié
- Tous les stubs (`repository`, `policy`, `service`, `controller`, `repository-with-logging`) utilisent désormais des variables dynamiques au lieu de `App\` en dur.
- Suite de tests reorganisée en deux suites PHPUnit : `Unit` et `Feature`.

### Supprimé
- Support Laravel 10 retiré de la CI et du `composer.json` (Laravel 10 est en fin de vie).

## [v0.1.0] - 2026-08-03

### Ajouté
- Command `make:pattern` : génère un CRUD complet (Model, Repository + interface, Service, Controller, Form Requests, API Resource, Policy, Feature test) depuis une seule commande Archeur.
- Option `--only=` pour sélectionner les couches à générer.
- Option `--force` pour écraser les fichiers existants.
- Command `make:pattern:undo` : annule le dernier run ; option `--id=` pour viser un run précis, avec alerte si un fichier a été modifié depuis sa génération.
- Command `make:pattern:history` : liste l'historique des générations (append-only dans `storage/app/make-pattern/history.json`).
- Logging via la façade `Log` (info/warning/error), avec canal dédié `make-pattern` si configuré dans l'app hôte, sinon fallback sur le canal par défaut.
- Option de config `wrap_repository_calls` (booléen, `false` par défaut) : enveloppe les appels `create`/`update`/`delete` du Repository dans un `try/catch` qui log et ré-émet les exceptions (stub `repository-with-logging`).