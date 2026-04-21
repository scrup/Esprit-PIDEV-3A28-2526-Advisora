# Preuve - Regle Equipe (Bundle Different) - Module Resource Back

Date: 2026-04-21

## Portee strictement respectee
- Les changements sont limites a la gestion `resource` cote back office.
- Aucun code des autres gestions (event, project, investment, security, shop flow metier) n'a ete modifie.
- Les routes ajoutees restent sous `/back/resources/...` (access-control deja reserve a `ROLE_GERANT` et `ROLE_ADMIN`).

## Ce qui a ete ajoute pour l'analyse ressources
- Moteur metier deterministic: `ResourceActionAnalysisService`.
- Stockage des resultats d'analyse: `ResourceAnalysisResultStore`.
- Couche chat low-token au-dessus du moteur: `ResourceAnalysisChatService`.
- Commande d'execution arriere-plan: `app:resource:analysis:run`.
- Vue back dediee: `back/resource/analysis.html.twig`.
- Export CSV des actions filtrees.
- Export PDF metier avance avec fallback HTML imprimable: `ResourceAnalysisPdfReportService` + `analysis_pdf.html.twig`.

## Mapping des exigences fonctionnelles
- Donnees source: stock total, stock reserve, statut, prix, fournisseur.
- Signaux calcules: stock critique, surstock, prix anormal, fournisseur a risque.
- Sortie actions: priorite, code action, impact metier, justification, confiance %, delai.
- Synthese + KPI globaux inclus.
- UX: bouton "Analyser", execution arriere-plan, affichage filtre, export.

## Regles bundles mentionnees par l'equipe (hors code)
- `StofDoctrineExtensionsBundle` (timestampable, sluggable, softdeleteable, Gedmo): installe et configure (`config/packages/stof_doctrine_extensions.yaml`).
- `DoctrineFixturesBundle` + `Faker` + classes Fixture: installes, fixture demo ajoutee `ResourceBackAnalysisFixture` (group `resource_back`).
- Tests/demo orientes specifiquement `shop bundle`: regle d'equipe conservee telle quelle (aucune modification des tests shop existants).

Ce fichier sert de preuve organisationnelle "hors code" pour la validation.
