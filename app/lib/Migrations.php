<?php
declare(strict_types=1);

/**
 * Database migrations. They run automatically on the first request after an update.
 *
 * Rules:
 * - Never edit or remove a migration that has shipped. Add a new one with the next number.
 * - Migrations only add things. They never delete data.
 * - Each one runs inside a transaction and is recorded in schema_version.
 */
final class Migrations
{
    /** @return array<int,callable> */
    private static function all(): array
    {
        return [
            1 => function (): void {
                $pdo = Db::pdo();
                $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL UNIQUE COLLATE NOCASE,
                    role TEXT NOT NULL DEFAULT 'editor',
                    name TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL,
                    last_login_at TEXT
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL DEFAULT ''
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS media (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    token TEXT NOT NULL UNIQUE,
                    kind TEXT NOT NULL,
                    original_ext TEXT NOT NULL,
                    web_ext TEXT NOT NULL,
                    original_name TEXT NOT NULL DEFAULT '',
                    size_bytes INTEGER NOT NULL DEFAULT 0,
                    width INTEGER NOT NULL DEFAULT 0,
                    height INTEGER NOT NULL DEFAULT 0,
                    variants TEXT NOT NULL DEFAULT '[]',
                    alt TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    uploaded_by TEXT NOT NULL DEFAULT ''
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS pages (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    parent_id INTEGER REFERENCES pages(id),
                    position INTEGER NOT NULL DEFAULT 0,
                    title TEXT NOT NULL,
                    slug TEXT NOT NULL UNIQUE,
                    status TEXT NOT NULL DEFAULT 'hidden',
                    show_in_menu INTEGER NOT NULL DEFAULT 1,
                    show_in_footer INTEGER NOT NULL DEFAULT 0,
                    is_home INTEGER NOT NULL DEFAULT 0,
                    header_media_id INTEGER,
                    seo_title TEXT NOT NULL DEFAULT '',
                    seo_description TEXT NOT NULL DEFAULT '',
                    blocks TEXT NOT NULL DEFAULT '[]',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    updated_by TEXT NOT NULL DEFAULT ''
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS pages_parent ON pages(parent_id, position)');
                $pdo->exec("CREATE TABLE IF NOT EXISTS page_versions (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    page_id INTEGER NOT NULL,
                    data TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    user_email TEXT NOT NULL DEFAULT '',
                    note TEXT NOT NULL DEFAULT ''
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS page_versions_page ON page_versions(page_id, id)');
                $pdo->exec("CREATE TABLE IF NOT EXISTS redirects (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    old_slug TEXT NOT NULL UNIQUE,
                    page_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS media_usage (
                    media_id INTEGER NOT NULL,
                    context TEXT NOT NULL,
                    ref_id INTEGER NOT NULL,
                    PRIMARY KEY (media_id, context, ref_id)
                )");
                $pdo->exec("CREATE TABLE IF NOT EXISTS auth_failures (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    ip_hash TEXT NOT NULL,
                    created_at INTEGER NOT NULL
                )");
                $pdo->exec('CREATE INDEX IF NOT EXISTS auth_failures_ip ON auth_failures(ip_hash, created_at)');
            },
            2 => function (): void {
                // First content: the home page, with clearly marked placeholders.
                if ((int) Db::value('SELECT COUNT(*) FROM pages') > 0) {
                    return;
                }
                $html = '<h2>Qui sommes-nous ?</h2>'
                    . '<p>[À COMPLÉTER : présentation de l\'association France État de Droit.]</p>'
                    . '<h2>Nos objectifs</h2>'
                    . '<p>[À COMPLÉTER : objet et actions de l\'association.]</p>'
                    . '<h2>Nous soutenir</h2>'
                    . '<p>[À COMPLÉTER : pourquoi faire un don ou adhérer.]</p>';
                $now = now_iso();
                $blocks = json_encode([['type' => 'text', 'html' => $html]], JSON_UNESCAPED_UNICODE);
                $id = Db::insert('pages', [
                    'parent_id' => null,
                    'position' => 1,
                    'title' => 'Accueil',
                    'slug' => 'accueil',
                    'status' => 'published',
                    'show_in_menu' => 0,
                    'show_in_footer' => 0,
                    'is_home' => 1,
                    'seo_title' => '',
                    'seo_description' => '',
                    'blocks' => $blocks,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'updated_by' => 'installation',
                ]);
                Pages::recordVersion($id, 'installation', 'Création');
            },
            3 => function (): void {
                // Association texts (November 2026). Only replaces the home page while it still
                // holds the installation placeholders: an edited home page is never overwritten.
                $home = Pages::home();
                if ($home !== null && strpos((string) $home['blocks'], '[À COMPLÉTER : présentation de l') !== false) {
                    $intro = '<h2>Qui sommes-nous ?</h2>'
                        . '<p>France État de Droit est née d\'un constat d\'urgence : la fragilisation croissante des normes'
                        . ' qui protègent les plus vulnérables et garantissent nos libertés fondamentales.</p>'
                        . '<p>Fondée par des professionnels et des citoyens engagés, issus essentiellement des mondes de la justice,'
                        . ' de la santé et du médico-social, notre association agit comme un rempart contre les dérives'
                        . ' populistes et illibérales.</p>'
                        . '<h2>Notre raison d\'être</h2>'
                        . '<p>L\'État de droit n\'est pas un concept abstrait : c\'est notre cadre protecteur commun. Il garantit concrètement :</p>'
                        . '<ul>'
                        . '<li>les droits des femmes et l\'accès aux soins pour tous ;</li>'
                        . '<li>la dignité des personnes incarcérées et les droits des personnes handicapées ;</li>'
                        . '<li>le respect des droits fondamentaux de tout être humain, français ou étranger ;</li>'
                        . '<li>la préservation de notre environnement.</li>'
                        . '</ul>'
                        . '<p>Lorsque ce cadre est contourné et fissuré, c\'est la protection de chacun qui s\'effondre.</p>'
                        . '<h2>Notre méthode : raisonner en droit</h2>'
                        . '<p>Loin des surenchères politiques, nous situons notre action au sein de l\'arc républicain.'
                        . ' Notre force réside dans la rigueur juridique.</p>';
                    $method = [
                        ['media_id' => null, 'url' => '', 'title' => '1. Analyser',
                            'text' => 'Passer au crible les actes litigieux au regard du droit en vigueur : Constitution, traités européens, grands principes du droit.'],
                        ['media_id' => null, 'url' => '', 'title' => '2. Agir en justice',
                            'text' => 'Agir de manière non violente, par des recours devant les instances supérieures (Conseil d\'État, Conseil constitutionnel, CEDH, CJUE), pour faire censurer les mesures contraires aux droits et libertés.'],
                        ['media_id' => null, 'url' => '', 'title' => '3. Rester indépendants',
                            'text' => 'Nous ne jugeons pas les opinions, mais la conformité des actes à la loi.'],
                    ];
                    $commitment = '<h2>Notre engagement</h2>'
                        . '<p>Qu\'il s\'agisse de défendre l\'indépendance de la justice ou de préserver les droits des personnes'
                        . ' privées de liberté, France État de Droit veille à ce que la force reste à la loi protectrice,'
                        . ' et non à l\'arbitraire.</p>'
                        . '<blockquote><p>« Chaque génération se croit vouée à refaire le monde. La mienne sait qu\'elle ne le refera pas.'
                        . ' Mais sa tâche est peut-être plus grande. Elle consiste à empêcher que le monde ne se défasse. »</p>'
                        . '<p>— Albert Camus</p></blockquote>';
                    $blocks = [
                        ['type' => 'text', 'html' => Sanitizer::html($intro)],
                        ['type' => 'cards', 'cards' => $method],
                        ['type' => 'text', 'html' => Sanitizer::html($commitment)],
                    ];
                    Db::update('pages', (int) $home['id'], [
                        'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'updated_at' => now_iso(),
                        'updated_by' => 'contenu initial',
                    ]);
                    Pages::recordVersion((int) $home['id'], 'contenu initial', 'Textes de l\'association');
                }
                $defaults = [
                    'tagline' => 'Raisonner en droit pour protéger les libertés fondamentales',
                    'site_description' => 'Association de professionnels et de citoyens engagés, issus de la justice, de la santé'
                        . ' et du médico-social, qui défend l\'État de droit et les libertés fondamentales par des recours juridiques non violents.',
                ];
                foreach ($defaults as $key => $value) {
                    $current = Db::value('SELECT value FROM settings WHERE key = ?', [$key]);
                    if ($current === null || $current === '') {
                        Settings::set($key, $value);
                    }
                }
            },
        ];
    }

    public static function latestVersion(): int
    {
        return max(array_keys(self::all()));
    }

    public static function currentVersion(): int
    {
        $exists = Db::value("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'schema_version'");
        if ($exists === null) {
            return 0;
        }
        return (int) Db::value('SELECT COALESCE(MAX(version), 0) FROM schema_version');
    }

    /** Apply missing migrations. Safe to call on every request. */
    public static function run(): void
    {
        self::prepareDataFolder();
        if (self::currentVersion() >= self::latestVersion()) {
            return;
        }
        Db::pdo()->exec('CREATE TABLE IF NOT EXISTS schema_version (
            version INTEGER PRIMARY KEY,
            applied_at TEXT NOT NULL
        )');
        foreach (self::all() as $version => $migration) {
            Db::transaction(function () use ($version, $migration): void {
                // Check again inside the lock: another request may have done it.
                $done = Db::value('SELECT version FROM schema_version WHERE version = ?', [$version]);
                if ($done !== null) {
                    return;
                }
                $migration();
                Db::insert('schema_version', ['version' => $version, 'applied_at' => now_iso()]);
            });
        }
    }

    /** Create the private folders, each with a "deny all" rule in case it sits in the web root. */
    public static function prepareDataFolder(): void
    {
        foreach (['', '/originals', '/sessions', '/logs', '/cache', '/backups'] as $sub) {
            ensure_dir(DATA_PATH . $sub);
        }
        $htaccess = DATA_PATH . '/.htaccess';
        if (!is_file($htaccess)) {
            @file_put_contents($htaccess, "Require all denied\n");
        }
        $mediaDir = Media::publicDir();
        ensure_dir($mediaDir);
        if (!is_file($mediaDir . '/.htaccess')) {
            @copy(APP_PATH . '/templates/media.htaccess', $mediaDir . '/.htaccess');
        }
    }
}
