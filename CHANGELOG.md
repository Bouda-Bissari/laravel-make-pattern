# Changelog

Toutes les modifications notables de ce projet seront documentées dans ce fichier.

Le format est basé sur [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) et ce projet respecte [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [v0.1.0] - 2026-08-03

### Ajouté
- Command `make:pattern` : génère un CRUD complet (Model, Repository + interface, Service, Controller, Form Requests, API Resource, Policy, Feature test) depuis une seule commande Archeur.
- Option `--only=` pour sélectionner les couches à générer.
- Option `--force` pour écraser les fichiers existants.
- Command `make:pattern:undo` : annule le dernier run ; option `--id=` pour viser un run précis, avec alerte si un fichier a été modifié depuis sa génération.
- Command `make:pattern:history` : liste l'historique des générations (append-only dans `storage/app/make-pattern/history.json`).
- Logging via la façade `Log` (info/warning/error), avec canal dédié `make-pattern` si configuré dans l'app hôte, sinon fallback sur le canal par défaut.
- Option de config `wrap_repository_calls` (booléen, `false` par défaut) : enveloppe les appels `create`/`update`/`delete` du Repository dans un `try/catch` qui log et ré-émet les exceptions (stub `repository-with-logging`).