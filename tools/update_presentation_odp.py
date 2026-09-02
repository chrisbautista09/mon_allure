#!/usr/bin/env python3
"""Apply the August 2026 project updates to a copy of the Mon Allure ODP deck."""

from __future__ import annotations

import sys
import tempfile
import zipfile
from pathlib import Path
from xml.etree import ElementTree as ET


DRAW_NS = "urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
TEXT_NS = "urn:oasis:names:tc:opendocument:xmlns:text:1.0"


def replace_in_page(page: ET.Element, replacements: dict[str, str]) -> None:
    for node in page.iter():
        if node.text:
            for old, new in replacements.items():
                node.text = node.text.replace(old, new)


def main(source: Path, destination: Path) -> None:
    with zipfile.ZipFile(source) as archive:
        content = archive.read("content.xml")
        root = ET.fromstring(content)
        pages = root.findall(f".//{{{DRAW_NS}}}page")
        if len(pages) != 24:
            raise RuntimeError(f"24 diapositives attendues, {len(pages)} trouvées")

        replace_in_page(pages[3], {
            "Les fonctionnalités secondaires restent séparées : la météo est en cours d’intégration, tandis que les statistiques graphiques avancées et l’administration restent dans la feuille de route.":
                "Le parcours comprend désormais la météo, les statistiques Chart.js, les jeux de démonstration et l’administration. Les audits finaux et l’industrialisation du déploiement restent des évolutions à présenter.",
        })
        replace_in_page(pages[6], {
            "Doctrine ORM • MariaDB": "Doctrine ORM • MySQL 8",
            "Symfony 8.1": "Symfony 8.1 et PHP 8.4+",
        })
        replace_in_page(pages[8], {
            "Turbo et Stimulus pour l’interactivité": "Turbo, Stimulus et Chart.js via Importmap",
            "Turbo facilite certaines navigations sans rechargement complet, tandis que Stimulus peut gérer des interactions ciblées sans construire une application JavaScript entièrement séparée.":
                "Turbo facilite les navigations, Stimulus gère les interactions ciblées et Chart.js 4.5.1 produit les graphiques. Chart.js est chargé par Importmap avec AssetMapper : aucun bundler Node.js n’est nécessaire et le déploiement reste cohérent avec l’interface Twig.",
            "Ce choix permet de conserver une interface cohérente avec Symfony tout en gardant une expérience fluide.":
                "Ce choix réduit la chaîne d’outillage et garde une expérience fluide. En contrepartie, le cycle de vie des graphiques lors des navigations Turbo est géré explicitement en JavaScript.",
        })
        replace_in_page(pages[18], {
            "259 tests • 1 793 assertions": "463 tests • 2 783 assertions",
            "259 tests et 1 793 assertions passent": "463 tests et 2 783 assertions passent",
        })
        replace_in_page(pages[19], {
            "Dans l’état actuel, les rôles administrateur et les voters ne sont pas encore implémentés. Ils seront nécessaires pour les futures fonctions d’administration, par exemple la gestion des comptes ou la modification des paramètres de l’algorithme. Je distingue donc clairement les protections déjà actives de celles qui restent dans la feuille de route.":
                "Le rôle administrateur est maintenant actif. Il protège la gestion des comptes, la supervision des plans générés, les commentaires et la modification des paramètres de l’algorithme. Les tests vérifient qu’un utilisateur standard ne peut pas atteindre ces pages.",
            "Les rôles administrateur et les voters restent à implémenter.":
                "Le rôle administrateur protège la supervision des comptes, plans et paramètres.",
            "EN COURS": "RÉALISÉ",
        })
        for node in pages[19].iter():
            if node.text and node.text.startswith("L’authentification repose"):
                node.text = (
                    "L’authentification repose sur le composant Security de Symfony. Le mot de passe est "
                    "haché et une session identifie l’utilisateur connecté. Les routes sensibles exigent "
                    "ROLE_USER, tandis que ROLE_ADMIN protège la gestion des comptes, la supervision des "
                    "plans générés, les commentaires et les paramètres de l’algorithme. Les données sont "
                    "toujours recherchées pour l’utilisateur courant. Les tests vérifient les redirections "
                    "des visiteurs, l’interdiction d’accès croisé et le refus des pages administrateur à "
                    "un utilisateur standard."
                )
        replace_in_page(pages[22], {
            "Le déploiement doit rendre l’environnement reproductible":
                "Docker rend maintenant l’environnement reproductible",
            "Conteneuriser": "Application",
            "PHP • serveur • base": "PHP 8.4 • Apache • Symfony",
            "Configurer": "Base de données",
            "variables et secrets": "MySQL 8 • volume persistant",
            "Déployer": "Orchestrer",
            "HTTPS • domaine": "Docker Compose • healthcheck",
            "Documenter": "Démarrer",
            "installation et reprise": "migrations automatiques • port 8080",
            "À ce jour, les fichiers Docker et la procédure de production ne sont pas présents dans le dépôt.":
                "Dockerfile, Compose, healthcheck MySQL et procédure README sont présents dans le dépôt.",
            "PRÉVU": "RÉALISÉ",
            "Je prévois de conteneuriser le serveur PHP et la base de données, puis de définir les services dans Docker Compose.":
                "J’ai ajouté un conteneur applicatif PHP 8.4 avec Apache et un service MySQL 8 orchestrés par Docker Compose.",
            "Les variables sensibles devront être configurées séparément pour le développement et la production.":
                "Les variables sont externalisées et un fichier d’exemple documente les secrets à remplacer.",
            "Le déploiement devra utiliser HTTPS et un nom de domaine, avec une procédure écrite pour installer les dépendances, exécuter les migrations et démarrer l’application.":
                "Le conteneur attend le healthcheck MySQL, puis applique les migrations avant de lancer Apache sur le port 8080.",
            "Cette partie n’est pas encore présente dans le dépôt actuel : je la présente donc comme un travail prévu, et non comme une réalisation terminée.":
                "Le README décrit la construction, le démarrage, les journaux, les comptes de démonstration et l’arrêt des services.",
            "Pour couvrir complètement la compétence de déploiement, je devrai ajouter les fichiers Docker, tester la reconstruction depuis un environnement vierge et rédiger une procédure de sauvegarde et de restauration de la base. Une automatisation CI/CD pourra ensuite être envisagée.":
                "Cette infrastructure couvre la reproductibilité locale et la démonstration. Pour une production complète, il restera à ajouter HTTPS, sauvegardes, supervision et automatisation CI/CD.",
        })
        replace_in_page(pages[23], {
            "259 tests • 1 793 assertions": "463 tests • 2 783 assertions",
            "Finaliser la météo, l’accessibilité, Docker et le déploiement.":
                "Finaliser les audits, HTTPS, sauvegardes et CI/CD.",
            "La suite de tests exécutée confirme 259 tests et 1 793 assertions.":
                "La suite de tests exécutée confirme 463 tests et 2 783 assertions.",
            "Les prochains jalons concernent maintenant l’expérience globale : terminer le service météo et son cache, réaliser les audits d’accessibilité et de performance, puis ajouter Docker et une procédure de déploiement reproductible.":
                "Les ajouts récents comprennent la météo et son cache, les graphiques Chart.js via Importmap, l’administration, les comptes de démonstration et Docker. Les prochains jalons sont les audits d’accessibilité et de performance, HTTPS, les sauvegardes et la CI/CD.",
        })

        updated_content = ET.tostring(root, encoding="utf-8", xml_declaration=True)
        destination.parent.mkdir(parents=True, exist_ok=True)
        with tempfile.NamedTemporaryFile(
            delete=False,
            suffix=".odp",
            dir=destination.parent,
        ) as temp:
            temp_path = Path(temp.name)

        try:
            with zipfile.ZipFile(temp_path, "w") as output:
                for item in archive.infolist():
                    data = updated_content if item.filename == "content.xml" else archive.read(item.filename)
                    compression = zipfile.ZIP_STORED if item.filename == "mimetype" else zipfile.ZIP_DEFLATED
                    output.writestr(item, data, compress_type=compression)
            temp_path.replace(destination)
        finally:
            temp_path.unlink(missing_ok=True)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        raise SystemExit("usage: update_presentation_odp.py SOURCE.odp DESTINATION.odp")
    main(Path(sys.argv[1]), Path(sys.argv[2]))
