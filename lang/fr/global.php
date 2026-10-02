<?php

return [
    'title' => 'eMCP',
    'permissions_group' => 'eMCP',
    'permission_access' => 'Accès à eMCP',
    'permission_manage' => 'Gérer les serveurs MCP',
    'permission_dispatch' => 'Exécuter des tâches MCP asynchrones',
    'menu_tokens' => 'Jetons MCP',
    'errors' => [
        'forbidden' => 'Interdit',
        'scope_denied' => 'Scope refusé',
        'server_not_found' => 'Serveur introuvable',
        'invalid_payload' => 'Payload invalide',
    ],
    'menu_settings' => 'Paramètres MCP',
    'settings' => [
        'title' => 'Paramètres MCP',
        'intro' => 'Ces interrupteurs s’appliquent à tout le site et sont enregistrés dans :path. Les autres options (scopes, limites, listes autorisées) se modifient directement dans ce fichier.',
        'save' => 'Enregistrer les paramètres',
        'saved' => 'Paramètres enregistrés.',
        'error_write' => 'Impossible d’enregistrer :path. Vérifiez que le serveur web peut écrire dans ce fichier.',
        'fields' => [
            'enable' => ['label' => 'Activer eMCP', 'hint' => 'Interrupteur général de tous les endpoints MCP.'],
            'mode_internal' => ['label' => 'Endpoint du manager', 'hint' => 'MCP via la session du manager.'],
            'mode_api' => ['label' => 'Endpoint API', 'hint' => 'Endpoint authentifié par token pour les agents externes.'],
            'require_scopes' => ['label' => 'Exiger les scopes du token', 'hint' => 'Chaque appel doit être couvert par les scopes de son token.'],
            'enable_write_tools' => ['label' => 'Autoriser les outils d’écriture', 'hint' => 'Les agents peuvent créer et modifier des documents et des éléments (evo.write.*). Le token doit aussi avoir le scope mcp:write.'],
            'self_service' => ['label' => 'Page libre-service des tokens', 'hint' => 'Les utilisateurs créent leurs propres tokens dans le manager.'],
            'rate_limit' => ['label' => 'Limitation du débit', 'hint' => 'Limite le nombre de requêtes par minute pour chaque utilisateur.'],
            'audit' => ['label' => 'Journal d’audit', 'hint' => 'Enregistrer chaque appel MCP dans le journal d’audit.'],
            'stream' => ['label' => 'Réponses en streaming', 'hint' => 'Autoriser les réponses en streaming (SSE).'],
        ],
    ],
];
