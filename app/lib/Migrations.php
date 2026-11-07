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
            4 => function (): void {
                // "Nos statuts" and "Adhérer" pages (November 2026). Each page is created only if
                // its address is free, so nothing an editor made is ever replaced.
                $statuts = '<p>Extraits des statuts de l\'association « France État de Droit ».</p>'
                    . '<h2>Article 1 : objet de l\'association</h2>'
                    . '<p>L\'objet de l\'association « France État de Droit » est la défense des libertés et des droits des personnes,'
                    . ' quelles qu\'elles soient, et de l\'État de droit qui garantit le respect de ces libertés et de ces droits.</p>'
                    . '<p>Par ses actions, l\'association « France État de Droit » s\'oppose à toute mesure individuelle ou collective'
                    . ' visant ou révélant un traitement discriminatoire, une action contraire à la préservation de l\'environnement,'
                    . ' ou une atteinte à l\'État de droit.</p>'
                    . '<p>La durée de l\'association est illimitée. Elle a son siège social au 19 avenue d\'Italie à Paris'
                    . ' (13e arrondissement).</p>'
                    . '<h2>Article 2 : moyens d\'action de l\'association</h2>'
                    . '<p>Les moyens privilégiés d\'action de l\'association « France État de Droit » sont l\'utilisation des voies'
                    . ' légales, les actions non violentes et la réunion de moyens matériels, financiers, techniques et humains'
                    . ' afin de faciliter :</p>'
                    . '<ul>'
                    . '<li>la défense et le soutien des personnes, institutions, établissements, mouvements et associations œuvrant'
                    . ' de manière non violente pour le maintien de l\'État de droit, le respect des libertés et des droits des'
                    . ' personnes, et la préservation de l\'environnement ;</li>'
                    . '<li>la mise en place d\'actions de connaissance des libertés et des droits des personnes dans le cadre'
                    . ' d\'un État de droit ;</li>'
                    . '<li>la création d\'espaces de dialogue, de réflexion et d\'analyse critique ;</li>'
                    . '<li>toute autre action utile à la réalisation de l\'objet de l\'association.</li>'
                    . '</ul>'
                    . '<p>À ce titre, l\'association peut avoir recours, notamment, à des actions en justice, des publications,'
                    . ' des manifestations, des expositions, des conférences, des cours, des bourses, des concours, des prix et'
                    . ' des récompenses, et des secours. Elle peut aussi organiser des comités locaux et des sections.</p>';
                $adherer = '<h2>Pourquoi nous rejoindre</h2>'
                    . '<p>En rejoignant « France État de Droit », vous décidez, pour préserver les libertés et l\'environnement,'
                    . ' de vous opposer à l\'application des idées populistes et illibérales.</p>'
                    . '<p>L\'État de droit n\'étant pas pour vous un concept abstrait, mais un cadre protecteur concret, vous'
                    . ' choisissez l\'action par le droit, la non-violence et au sein de l\'arc républicain.</p>'
                    . '<h2>Votre engagement</h2>'
                    . '<p>Vous adhérez aux valeurs inscrites dans les <a href="/statuts">statuts</a> et la charte des principes'
                    . ' d\'action de l\'association, et vous vous engagez à les respecter.</p>'
                    . '<h2>Adhérer en ligne</h2>'
                    . '<p>L\'adhésion se fait sur HelloAsso, la plateforme de paiement des associations. Le paiement est sécurisé.</p>'
                    . '<p><a class="btn" href="' . Settings::adhesionUrl() . '">Adhérer sur HelloAsso</a></p>';
                $pages = [
                    ['Nos statuts', 'statuts', $statuts, 'Extraits des statuts de France État de Droit : objet de l\'association et moyens d\'action.'],
                    ['Adhérer', 'adherer', $adherer, 'Rejoindre France État de Droit : agir par le droit, la non-violence et au sein de l\'arc républicain.'],
                ];
                foreach ($pages as $spec) {
                    list($title, $slug, $html, $description) = $spec;
                    if (Pages::slugTaken($slug, null) || Db::value('SELECT page_id FROM redirects WHERE old_slug = ?', [$slug]) !== null) {
                        continue;
                    }
                    $now = now_iso();
                    $id = Db::insert('pages', [
                        'parent_id' => null,
                        'position' => (int) Db::value('SELECT COALESCE(MAX(position), 0) + 1 FROM pages WHERE parent_id IS NULL'),
                        'title' => $title,
                        'slug' => $slug,
                        'status' => 'published',
                        'show_in_menu' => 1,
                        'show_in_footer' => 0,
                        'is_home' => 0,
                        'seo_title' => '',
                        'seo_description' => $description,
                        'blocks' => json_encode([['type' => 'text', 'html' => Sanitizer::html($html)]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                        'updated_by' => 'contenu initial',
                    ]);
                    Pages::recordVersion($id, 'contenu initial', 'Textes de l\'association');
                }
                $footer = Db::value("SELECT value FROM settings WHERE key = 'footer_text'");
                if ($footer === null || $footer === '') {
                    Settings::set('footer_text', 'Siège social : 19 avenue d\'Italie, 75013 Paris.');
                }
            },
            5 => function (): void {
                // The association's motto (from its logo) becomes the tagline, unless an editor changed it.
                $tagline = Db::value("SELECT value FROM settings WHERE key = 'tagline'");
                if ($tagline === null || $tagline === '' || $tagline === 'Raisonner en droit pour protéger les libertés fondamentales') {
                    Settings::set('tagline', 'La loi veille et protège');
                }
            },
            6 => function (): void {
                // "L'État de droit" page, condensed from training module 1 (November 2026).
                // Created only if its address is free; it goes first in the menu, after the home page.
                $slug = 'etat-de-droit';
                if (Pages::slugTaken($slug, null) || Db::value('SELECT page_id FROM redirects WHERE old_slug = ?', [$slug]) !== null) {
                    return;
                }
                $intro = '<p>L\'expression revient sans cesse dans le débat public, souvent sans être définie. Cette page en donne'
                    . ' l\'essentiel : ce qu\'est un État de droit, comment il fonctionne, d\'où il vient, et où il est fragile en France.</p>'
                    . '<h2>Trois situations ordinaires</h2>'
                    . '<p><strong>Votre commune vous refuse un permis de construire.</strong> Vous avez deux mois pour saisir le tribunal'
                    . ' administratif. Si la décision du maire est illégale, le juge l\'annule, et le maire, bien qu\'élu, doit réexaminer votre demande.</p>'
                    . '<p><strong>Vous êtes placé en garde à vue.</strong> On doit vous notifier les motifs de la mesure, votre droit de vous taire,'
                    . ' d\'être assisté par un avocat, de faire prévenir un proche et d\'être examiné par un médecin. Sinon, la procédure peut'
                    . ' être annulée.</p>'
                    . '<p><strong>Le Parlement vote une loi.</strong> Soixante députés saisissent le Conseil constitutionnel, qui juge que'
                    . ' certaines dispositions portent une atteinte disproportionnée à une liberté garantie par la Constitution. Il les censure :'
                    . ' elles n\'entreront jamais en vigueur, bien que votées par des élus.</p>'
                    . '<p>Dans les trois cas, une autorité publique voulait agir, et le droit l\'en a empêchée. Pas le droit en général, mais un'
                    . ' mécanisme précis : une règle supérieure, et un juge indépendant pour la faire respecter.</p>'
                    . '<h2>La définition</h2>'
                    . '<blockquote><p>L\'État de droit est un système institutionnel dans lequel la puissance publique est elle-même soumise'
                    . ' au droit, sous le contrôle de juges indépendants.</p></blockquote>'
                    . '<p>Tous les régimes, y compris les pires, obligent les citoyens à respecter la loi. L\'État de droit, lui, oblige'
                    . ' <strong>l\'État</strong> à respecter la loi, y compris lorsqu\'il est démocratiquement élu et majoritaire.</p>'
                    . '<h2>Ce que l\'État de droit n\'est pas</h2>'
                    . '<ul>'
                    . '<li><strong>Ce n\'est pas « beaucoup de lois ».</strong> Les régimes autoritaires légifèrent énormément. Ce qui compte'
                    . ' n\'est pas le volume des règles, mais le fait qu\'elles s\'imposent au pouvoir lui-même.</li>'
                    . '<li><strong>Ce n\'est pas la démocratie.</strong> La démocratie répond à la question « qui décide ? » : le peuple,'
                    . ' directement ou par ses représentants. L\'État de droit répond à une autre question : « dans quelles limites ? ».'
                    . ' Un régime peut rester électoralement démocratique et cesser d\'être un État de droit, comme en Hongrie et,'
                    . ' pendant plusieurs années, en Pologne.</li>'
                    . '<li><strong>Ce n\'est pas le « gouvernement des juges ».</strong> Le juge qui censure une loi n\'impose pas sa préférence :'
                    . ' il applique une norme que le pouvoir politique a lui-même adoptée, comme la Constitution ou un traité ratifié.</li>'
                    . '</ul>'
                    . '<h2>Les trois piliers</h2>'
                    . '<p>L\'État de droit repose sur trois éléments indissociables. Aucun ne suffit seul.</p>';
                $pillars = [
                    ['media_id' => null, 'url' => '', 'title' => 'Les droits fondamentaux',
                        'text' => 'Le contenu protégé : des droits que le pouvoir ne peut pas supprimer par une décision ordinaire.'
                            . ' Sans eux, la hiérarchie des normes peut organiser proprement un ordre oppressif.'],
                    ['media_id' => null, 'url' => '', 'title' => 'La hiérarchie des normes',
                        'text' => 'Une place au sommet : les droits fondamentaux sont inscrits dans des textes supérieurs à la loi ordinaire.'
                            . ' Sans elle, les droits sont révocables par la première majorité venue.'],
                    ['media_id' => null, 'url' => '', 'title' => 'L\'indépendance des juges',
                        'text' => 'Une sanction effective : un tiers impartial constate la violation et l\'annule.'
                            . ' Sans elle, les deux autres piliers restent des déclarations. C\'est le pilier le plus attaqué aujourd\'hui.'],
                ];
                $body = '<p>Les trois se tiennent l\'un l\'autre :</p>'
                    . '<ul>'
                    . '<li>les droits fondamentaux sont garantis par leur place au sommet de la hiérarchie des normes ;</li>'
                    . '<li>les droits fondamentaux sont garantis par des juges indépendants ;</li>'
                    . '<li>la hiérarchie des normes est elle-même garantie par des juges indépendants.</li>'
                    . '</ul>'
                    . '<p>Retirez un seul élément, et les deux autres cessent de protéger quoi que ce soit. C\'est pourquoi l\'État de droit'
                    . ' se défend comme un ensemble.</p>'
                    . '<h2>Une conquête récente, et réversible</h2>'
                    . '<p>Dans les monarchies anciennes, le roi, donc l\'État, n\'est pas soumis au droit : il en est la source.'
                    . ' L\'État de droit s\'est construit par étapes, contre le pouvoir arbitraire.</p>'
                    . '<ul>'
                    . '<li><strong>1679 et 1689, Angleterre :</strong> l\'Habeas Corpus interdit la détention arbitraire, le Bill of Rights'
                    . ' soumet la Couronne au Parlement.</li>'
                    . '<li><strong>1748 :</strong> Montesquieu, dans <em>De l\'esprit des lois</em>, pose la séparation des pouvoirs :'
                    . ' « le pouvoir arrête le pouvoir ».</li>'
                    . '<li><strong>1789 :</strong> la Déclaration des droits de l\'homme et du citoyen attache les droits à la personne,'
                    . ' et non plus au rang social.</li>'
                    . '<li><strong>1889 :</strong> par l\'arrêt Cadot, le Conseil d\'État met fin à la « théorie du ministre-juge ».'
                    . ' Jusque-là, quand vous aviez un litige avec l\'administration, c\'est l\'administration qui vous jugeait.</li>'
                    . '<li><strong>1901 :</strong> le juriste Léon Duguit emploie l\'expression « État de droit » en français.</li>'
                    . '<li><strong>Après 1945 :</strong> la notion se généralise. Le régime nazi s\'était installé en utilisant les procédures'
                    . ' légales, puis en les modifiant légalement. L\'après-guerre exige donc aussi que le <em>contenu</em> des lois respecte'
                    . ' des droits fondamentaux hors d\'atteinte du législateur.</li>'
                    . '</ul>'
                    . '<p>Ce qui a été construit en un siècle et demi peut être défait en quelques années.</p>'
                    . '<h2>Les droits fondamentaux</h2>'
                    . '<p>Ce sont les droits de l\'homme traduits en normes juridiques, c\'est-à-dire que l\'on peut faire valoir devant un juge.'
                    . ' On en distingue trois générations :</p>'
                    . '<ul>'
                    . '<li><strong>les droits civils et politiques</strong> (égalité, liberté, propriété, sûreté) : l\'État doit ne pas en'
                    . ' entraver l\'exercice ;</li>'
                    . '<li><strong>les droits sociaux et économiques</strong> (emploi, santé, enseignement gratuit, droit de grève, liberté'
                    . ' syndicale) : l\'État doit agir pour les rendre possibles ;</li>'
                    . '<li><strong>les droits collectifs et environnementaux</strong> (Charte de l\'environnement de 2005, abolition de la'
                    . ' peine de mort en 2007, liberté de recourir à l\'IVG en 2024).</li>'
                    . '</ul>'
                    . '<p>Les premiers sont directement invocables devant un juge. Les suivants passent le plus souvent par des lois et des'
                    . ' décrets d\'application : le droit à l\'emploi ne permet pas de faire condamner l\'État parce que l\'on est au chômage,'
                    . ' mais le juge peut sanctionner la violation des textes qui le mettent en œuvre.</p>'
                    . '<p>L\'État de droit protège toutes les personnes présentes sur le territoire. Certaines différences de traitement sont'
                    . ' possibles pour les étrangers (séjour, droit de vote, fonction publique), mais aucune pour le droit à un recours effectif'
                    . ' devant un juge indépendant. Le contentieux des étrangers représente plus du tiers des affaires des tribunaux'
                    . ' administratifs : l\'État de droit protège d\'abord ceux qui ont le moins de pouvoir.</p>'
                    . '<h2>La hiérarchie des normes</h2>'
                    . '<p>Chaque règle doit respecter les règles qui lui sont supérieures, sinon un juge peut l\'annuler. C\'est la pyramide'
                    . ' décrite par le juriste Hans Kelsen :</p>'
                    . '<ol>'
                    . '<li><strong>la Constitution</strong> de 1958 et les textes auxquels elle renvoie (Déclaration de 1789, préambule'
                    . ' de 1946, Charte de l\'environnement), contrôlée par le Conseil constitutionnel ;</li>'
                    . '<li><strong>les traités</strong> ratifiés, dont la Convention européenne des droits de l\'homme et le droit de'
                    . ' l\'Union européenne ;</li>'
                    . '<li><strong>les lois</strong> votées par le Parlement ;</li>'
                    . '<li><strong>les règlements</strong> : décrets et arrêtés, contrôlés par le juge administratif.</li>'
                    . '</ol>'
                    . '<p>Inscrire un droit dans la Constitution, c\'est le retirer du jeu majoritaire : une simple loi ne peut plus le supprimer.'
                    . ' La Constitution se révise par son article 89, qui exige un vote identique des deux chambres puis un référendum'
                    . ' ou une majorité des trois cinquièmes du Congrès. Mais l\'article 11, prévu pour faire adopter une loi par référendum,'
                    . ' a déjà servi à la réviser, en 1962. C\'est le principal point de fragilité de nos institutions.</p>'
                    . '<h2>L\'indépendance des juges</h2>'
                    . '<p>Un droit dont aucun juge indépendant ne peut sanctionner la violation n\'est pas un droit : c\'est une déclaration.'
                    . ' En France, l\'indépendance de la justice a valeur constitutionnelle. Le Conseil constitutionnel l\'a définie ainsi'
                    . ' en 2007 :</p>'
                    . '<blockquote><p>« Le principe d\'indépendance de la justice vise à garantir la possibilité de prendre des décisions'
                    . ' à l\'abri de toute instruction ou pression. Ni le législateur, ni le Gouvernement, non plus qu\'aucune autorité'
                    . ' administrative, ne peuvent empiéter sur les fonctions des juges. »</p></blockquote>'
                    . '<p>Indépendance ne veut pas dire irresponsabilité : le juge applique le droit, y compris quand il le désapprouve,'
                    . ' motive ses décisions, et celles-ci peuvent faire l\'objet d\'un recours. Elle n\'empêche pas non plus de réformer la'
                    . ' justice. Ce qui menace l\'État de droit, c\'est de modifier le statut de ceux qui jugent.</p>'
                    . '<p>Plusieurs États européens ont affaibli leur justice en suivant la même séquence :</p>'
                    . '<ol>'
                    . '<li>contester la légitimité des juges, « qui ne sont pas élus » ;</li>'
                    . '<li>présenter leurs décisions comme « idéologiques » ;</li>'
                    . '<li>modifier la façon dont ils sont nommés ;</li>'
                    . '<li>réduire les voies de recours et les délais ;</li>'
                    . '<li>réduire les moyens de la justice.</li>'
                    . '</ol>'
                    . '<blockquote><p>Le juge n\'oppose pas sa volonté au peuple. Il oppose au pouvoir une norme que ce pouvoir a lui-même'
                    . ' souscrite. L\'élection est la porte d\'entrée du pouvoir ; l\'État de droit est ce qui empêche le pouvoir de'
                    . ' condamner la sortie.</p></blockquote>'
                    . '<h2>Les failles de l\'État de droit en France</h2>'
                    . '<p>Une faille n\'est pas une violation : c\'est une porte que le droit laisse entrouverte. Chacun de ces mécanismes est'
                    . ' légal, mais son usage systématique affaiblirait l\'État de droit.</p>'
                    . '<ul>'
                    . '<li><strong>Le détour par l\'article 11</strong> : réviser la Constitution sans les garanties de l\'article 89.</li>'
                    . '<li><strong>Les pouvoirs exceptionnels de l\'article 16</strong> : le Président apprécie seul la menace et la durée,'
                    . ' sans contrôle contraignant.</li>'
                    . '<li><strong>L\'exception qui devient permanente</strong> : des mesures de l\'état d\'urgence de 2015 sont entrées dans le'
                    . ' droit commun en 2017.</li>'
                    . '<li><strong>Les ordonnances de l\'article 38</strong> : le Parlement autorise, le Gouvernement rédige, et la ratification'
                    . ' vient souvent sans débat de fond.</li>'
                    . '<li><strong>Un parquet lié à l\'exécutif</strong> : les procureurs sont placés sous l\'autorité du ministre de la Justice.'
                    . ' En 2010, la Cour européenne des droits de l\'homme a jugé qu\'ils n\'étaient pas une « autorité judiciaire »'
                    . ' indépendante (arrêt Moulin c. France).</li>'
                    . '<li><strong>Une justice sous-dotée</strong> : un droit qu\'on ne peut pas faire juger dans un délai raisonnable reste'
                    . ' un droit sur le papier.</li>'
                    . '</ul>'
                    . '<h2>Pour aller plus loin</h2>'
                    . '<ul>'
                    . '<li><a href="https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000571356/">La Constitution du 4 octobre 1958</a>'
                    . ' (Légifrance)</li>'
                    . '<li><a href="https://www.legifrance.gouv.fr/loda/id/JORFTEXT000000697056">La Déclaration des droits de l\'homme et'
                    . ' du citoyen de 1789</a>, notamment son article 16</li>'
                    . '<li><a href="https://www.conseil-etat.fr/decisions-de-justice/jurisprudence/les-grandes-decisions-depuis-1873/conseil-d-etat-13-decembre-1889-cadot">L\'arrêt'
                    . ' Cadot du Conseil d\'État, 13 décembre 1889</a></li>'
                    . '<li><a href="https://www.conseil-constitutionnel.fr/decision/2007/2007551DC.htm">La décision n° 2007-551 DC du'
                    . ' Conseil constitutionnel</a> sur l\'indépendance de la justice</li>'
                    . '<li><a href="https://www.vie-publique.fr">vie-publique.fr</a>, pour les définitions institutionnelles</li>'
                    . '<li>Jacques Chevallier, <em>L\'État de droit</em>, LGDJ, collection « Clefs » : la synthèse de référence en français</li>'
                    . '</ul>'
                    . '<h2>Se former avec nous</h2>'
                    . '<p>Ce texte résume le premier module du parcours de formation de France État de Droit. Les quatre modules permettent'
                    . ' de définir l\'État de droit, d\'identifier ses opposants et leurs arguments, de communiquer en tant que membre de'
                    . ' l\'association, et de mobiliser les moyens juridiques qu\'il met à disposition. L\'adhésion ouvre l\'accès à l\'ensemble'
                    . ' du parcours et aux échanges avec les formateurs.</p>'
                    . '<p><a class="btn" href="/adherer">Adhérer à l\'association</a></p>';
                $blocks = [
                    ['type' => 'text', 'html' => Sanitizer::html($intro)],
                    ['type' => 'cards', 'cards' => $pillars],
                    ['type' => 'text', 'html' => Sanitizer::html($body)],
                ];
                // Right after the home page in the menu: shift the other top-level pages down by one.
                $homePosition = (int) Db::value('SELECT COALESCE(MAX(position), 0) FROM pages WHERE parent_id IS NULL AND is_home = 1');
                Db::run('UPDATE pages SET position = position + 1 WHERE parent_id IS NULL AND position > ?', [$homePosition]);
                $now = now_iso();
                $id = Db::insert('pages', [
                    'parent_id' => null,
                    'position' => $homePosition + 1,
                    'title' => 'L\'État de droit',
                    'slug' => $slug,
                    'status' => 'published',
                    'show_in_menu' => 1,
                    'show_in_footer' => 0,
                    'is_home' => 0,
                    'seo_title' => 'Qu\'est-ce que l\'État de droit ?',
                    'seo_description' => 'L\'État de droit expliqué simplement : définition, les trois piliers (droits fondamentaux,'
                        . ' hiérarchie des normes, indépendance des juges), son histoire et ses failles en France.',
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                    'updated_by' => 'contenu initial',
                ]);
                Pages::recordVersion($id, 'contenu initial', 'Module 1 de la formation');
            },
            7 => function (): void {
                // The triangle and Kelsen pyramid diagrams from training module 1, added to the media
                // library and placed in the "L'État de droit" page, only while that page is untouched.
                $page = Pages::findBySlug('etat-de-droit');
                if ($page === null || $page['updated_by'] !== 'contenu initial' || strpos((string) $page['blocks'], 'data-media-id') !== false) {
                    return;
                }
                $blocks = Pages::blocks($page);
                if (count($blocks) !== 3 || ($blocks[2]['type'] ?? '') !== 'text') {
                    return;
                }
                $images = [
                    'triangle' => ['triangle-etat-de-droit.png', 'Le triangle de l\'État de droit : les droits fondamentaux'
                        . ' sont garantis par leur place au sommet de la hiérarchie des normes et par des juges indépendants ;'
                        . ' la hiérarchie des normes est garantie par des juges indépendants.'],
                    'pyramid' => ['pyramide-de-kelsen.png', 'La pyramide de Kelsen, du sommet à la base : bloc constitutionnel,'
                        . ' bloc conventionnel (traités, droit européen), bloc législatif (lois, ordonnances), principes généraux'
                        . ' du droit, bloc réglementaire (décrets, arrêtés, circulaires).'],
                ];
                $tags = [];
                try {
                    foreach ($images as $key => $spec) {
                        list($file, $alt) = $spec;
                        $media = Media::store(APP_PATH . '/content/' . $file, $file, 'contenu initial');
                        Media::setAlt((int) $media['id'], $alt);
                        $tags[$key] = '<p><img src="' . Media::url($media) . '" data-media-id="' . $media['id'] . '" alt="' . e($alt) . '"></p>';
                    }
                } catch (Throwable $error) {
                    // Never block the site over an image: leave the page as it is.
                    error_log('Migration 7: ' . $error->getMessage());
                    return;
                }
                $html = (string) $blocks[2]['html'];
                $html = preg_replace('~</ul>~', '</ul>' . $tags['triangle'], $html, 1);
                $html = preg_replace('~(contrôlés par le juge administratif\.</li></ol>)~u', '$1' . $tags['pyramid'], $html, 1);
                $blocks[2]['html'] = Sanitizer::html((string) $html);
                Db::update('pages', (int) $page['id'], [
                    'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'updated_at' => now_iso(),
                ]);
                Pages::updateMediaUsage((int) $page['id']);
                Pages::recordVersion((int) $page['id'], 'contenu initial', 'Schémas du module 1');
            },
            8 => function (): void {
                // November 2026: full statutes (as amended on 19 November 2025), the charter of
                // action principles, membership fees, and the association's official identifiers
                // (INSEE notice of 4 May 2025). Each change applies only to untouched content.
                $q = '« France État de Droit »';
                $statuts = '<p>Statuts de l\'association ' . $q . ', approuvés par l\'assemblée générale constitutive du 6 avril 2025'
                    . ' et modifiés par l\'assemblée générale extraordinaire du 19 novembre 2025.</p>'
                    . '<h2>I. But et composition de l\'association</h2>'
                    . '<h3>Article 1 : objet de l\'association</h3>'
                    . '<p>L\'objet de l\'association ' . $q . ' est la défense des libertés et des droits des personnes, quelles qu\'elles'
                    . ' soient, et de l\'État de droit qui garantit le respect de ces libertés et de ces droits.</p>'
                    . '<p>Par ses actions, l\'association ' . $q . ' s\'oppose à toute mesure individuelle ou collective visant ou révélant'
                    . ' un traitement discriminatoire, une action contraire à la préservation de l\'environnement, ou une atteinte à'
                    . ' l\'État de droit.</p>'
                    . '<p>La durée de l\'association est illimitée. Elle a son siège social au 19 avenue d\'Italie à Paris'
                    . ' (13e arrondissement), dans le département de la Seine. Le siège social peut être transféré par décision du conseil'
                    . ' d\'administration votée par les deux tiers de ses membres sur proposition de trois de ses membres, dont le président.</p>'
                    . '<h3>Article 2 : moyens d\'action de l\'association</h3>'
                    . '<p>Les moyens privilégiés d\'action de l\'association ' . $q . ' sont l\'utilisation des voies légales, les actions'
                    . ' non-violentes et la réunion de moyens matériels, financiers, techniques et humains afin de faciliter :</p>'
                    . '<ul>'
                    . '<li>la défense et le soutien des personnes, institutions, établissements, mouvements et associations œuvrant de'
                    . ' manière non-violente pour le maintien de l\'État de droit, le respect des libertés et des droits des personnes,'
                    . ' et la préservation de l\'environnement ;</li>'
                    . '<li>la mise en place d\'actions de connaissance des libertés et des droits des personnes dans le cadre d\'un'
                    . ' État de droit ;</li>'
                    . '<li>la création d\'espaces de dialogue, de réflexion et d\'analyse critique ;</li>'
                    . '<li>toute autre action utile à la réalisation de l\'objet de l\'association.</li>'
                    . '</ul>'
                    . '<p>À ce titre, l\'association peut avoir recours, notamment, à des actions en justice, des publications, des'
                    . ' manifestations, des expositions, des conférences, des cours, des bourses, des concours, des prix et des récompenses,'
                    . ' et des secours. Elle peut aussi organiser des comités locaux et des sections.</p>'
                    . '<h3>Article 3 : valeurs de l\'association</h3>'
                    . '<p>L\'association ' . $q . ' est apolitique, areligieuse et a-communautaire. Elle défend les libertés et les droits'
                    . ' des personnes et non la teneur de leurs opinions politiques, philosophiques ou religieuses, ou les signes de leur'
                    . ' appartenance communautaire. Elle rappelle que le racisme, l\'antisémitisme et le négationnisme ne sont pas des'
                    . ' opinions mais des délits punis par la loi.</p>'
                    . '<p>Elle promeut la solidarité, le respect de la dignité des personnes, la tolérance et le respect des différences,'
                    . ' la rencontre, l\'amitié et le partage entre les personnes et les peuples pour maintenir les apports de l\'humanisme'
                    . ' et favoriser le droit de vivre dans un monde pacifique et juste et dans un environnement durable et respectueux'
                    . ' de la santé.</p>'
                    . '<p>Elle porte une attention particulière, d\'une part, au maintien des libertés et des droits des femmes, des'
                    . ' personnes étrangères, des personnes placées dans des lieux de privation de liberté, des personnes handicapées et'
                    . ' des personnes quant au choix de leur orientation sexuelle, et d\'autre part, au droit de vivre dans un environnement'
                    . ' durable et respectueux de la santé.</p>'
                    . '<p>L\'association ' . $q . ' s\'oppose aux traitements discriminatoires fondés, notamment, sur une appartenance vraie'
                    . ' ou supposée d\'une personne, femme, homme, autre identité de genre ou enfant, à une origine géographique, à une'
                    . ' nation, et à une ethnie ou une prétendue « race », sur ses opinions politiques, syndicales, philosophiques ou'
                    . ' religieuses, sur son orientation sexuelle ou son identité de genre, sur son âge, son patronyme, sa situation de'
                    . ' famille ou de grossesse, son état de santé, son apparence physique ou son handicap.</p>'
                    . '<p>Elle attache une importance capitale aux principes d\'égalité des sujets de droit, de séparation des pouvoirs et'
                    . ' de hiérarchie des normes qui caractérisent l\'existence d\'un État de droit et d\'une démocratie. À ce titre,'
                    . ' l\'association s\'appuie particulièrement sur les textes de valeur supérieure qui garantissent les libertés et les'
                    . ' droits des personnes et la préservation de l\'environnement, notamment ceux de natures constitutionnelle et'
                    . ' européenne ou internationale applicables en droit interne. Elle s\'oppose donc à toute action remettant en cause'
                    . ' ce cadre constitutionnel et juridique qui assure une protection des personnes et de l\'environnement.</p>'
                    . '<p>Afin de représenter les valeurs de l\'association, son logo est composé des éléments suivants : une balance'
                    . ' équilibrée, symbolisant l\'État de droit, et un hexagone et/ou une évocation de la Marianne, symbolisant la France'
                    . ' (la métropole et ses outremers). La devise de l\'association est « la loi veille et protège », soit en latin'
                    . ' « <em>lex custodit</em> ».</p>'
                    . '<h3>Article 4 : composition de l\'association</h3>'
                    . '<p>L\'association se compose de :</p>'
                    . '<ul>'
                    . '<li>membres adhérents personnes physiques, c\'est-à-dire les personnes physiques majeures à jour de leur cotisation'
                    . ' annuelle ;</li>'
                    . '<li>membres adhérents personnes morales, c\'est-à-dire les personnes morales dont l\'action s\'inscrit dans l\'objet'
                    . ' de l\'association, à jour de leur cotisation annuelle, représentées par leur représentant légal ou par une personne'
                    . ' désignée à cet effet ;</li>'
                    . '<li>membres d\'honneur.</li>'
                    . '</ul>'
                    . '<p>En adhérant à l\'association, chaque membre reconnaît avoir pris connaissance de ses statuts, notamment les valeurs'
                    . ' qui y sont inscrites, de son règlement intérieur, et de ses chartes, s\'il y a lieu, et déclare s\'engager à les'
                    . ' respecter.</p>'
                    . '<p>Pour être membre, il faut être agréé par le conseil d\'administration.</p>'
                    . '<p>La cotisation annuelle est de :</p>'
                    . '<ul>'
                    . '<li>20 € pour les membres adhérents personnes physiques de 30 ans et plus ;</li>'
                    . '<li>10 € pour les membres adhérents personnes physiques de moins de 30 ans et les bénéficiaires des minima sociaux ;</li>'
                    . '<li>100 € pour les membres adhérents personnes morales.</li>'
                    . '</ul>'
                    . '<p>Les montants de ces cotisations annuelles peuvent être modifiés par décision de l\'assemblée générale.</p>'
                    . '<p>Le titre de membre d\'honneur peut être décerné par le conseil d\'administration aux personnes physiques et morales'
                    . ' qui rendent ou qui ont rendu des services signalés à l\'association. Ce titre confère aux personnes qui l\'ont obtenu'
                    . ' le droit de faire partie de l\'assemblée générale pendant les deux années qui suivent leur désignation sans être'
                    . ' tenues de payer une cotisation.</p>'
                    . '<h3>Article 5 : perte de la qualité de membre de l\'association</h3>'
                    . '<p>La qualité de membre de l\'association se perd :</p>'
                    . '<ol>'
                    . '<li>par la démission ;</li>'
                    . '<li>par le non-renouvellement de la cotisation annuelle constaté au 30 juin de l\'année suivant celle de l\'adhésion ;</li>'
                    . '<li>par la radiation prononcée par le conseil d\'administration pour motifs graves, notamment au regard du non-respect'
                    . ' des engagements cités à l\'article précédent. Le membre intéressé est préalablement invité à fournir ses explications ;</li>'
                    . '<li>par le décès du membre adhérent personne physique ;</li>'
                    . '<li>par la disparition du membre adhérent personne morale.</li>'
                    . '</ol>'
                    . '<h2>II. Administration et fonctionnement</h2>'
                    . '<h3>Article 6 : conseil d\'administration</h3>'
                    . '<p>L\'association est administrée par un conseil dont le nombre des membres, fixé par délibération de l\'assemblée'
                    . ' générale, est compris entre 6 et 12 membres. Il est recherché une représentation équilibrée entre les femmes et les'
                    . ' hommes au sein du conseil d\'administration. Les membres du conseil d\'administration sont élus pour 4 ans au scrutin'
                    . ' secret par l\'assemblée générale et choisis dans les catégories de membres dont se compose cette assemblée à jour de'
                    . ' leur cotisation pour les deux années précédentes. Cette exigence ne trouve à s\'appliquer qu\'à compter du premier'
                    . ' renouvellement du conseil. Le renouvellement du conseil a lieu par moitié tous les 2 ans. Pour le premier'
                    . ' renouvellement par moitié du conseil, les membres sortants sont tirés au sort.</p>'
                    . '<p>En cas d\'existence d\'agents salariés et rétribués par l\'association, ceux-ci peuvent être membres de l\'association'
                    . ' et élus au conseil d\'administration. Toutefois, ils ne peuvent occuper les fonctions de président.e, vice-président.e,'
                    . ' secrétaire ou trésorier.ère. Leur nombre maximum ne peut pas dépasser le quart de l\'effectif total du conseil. Dans le'
                    . ' cas où le nombre des candidat.e.s, salarié.e.s et rétribué.e.s par l\'association, ayant obtenu les voix nécessaires'
                    . ' pour être élu.e.s, dépasserait cette proportion, seul.e.s sont proclamé.e.s élu.e.s, dans la limite statutairement'
                    . ' définie, les candidat.e.s qui ont obtenu le plus de voix.</p>'
                    . '<p>En cas de cessation de fonction d\'un membre du conseil d\'administration pour quelque cause que ce soit, le conseil'
                    . ' peut pourvoir provisoirement à son remplacement par un membre de l\'association ayant la qualité de membre fondateur'
                    . ' ou de membre à jour de sa cotisation pour les deux années précédentes. Il est procédé au remplacement définitif par la'
                    . ' plus prochaine assemblée générale. Les pouvoirs du membre ainsi désigné prennent fin à l\'époque où devrait normalement'
                    . ' expirer le mandat du membre remplacé.</p>'
                    . '<p>Les membres sortants sont rééligibles.</p>'
                    . '<p>Le conseil d\'administration choisit parmi ses membres, au scrutin secret, un bureau composé d\'un.e président.e,'
                    . ' d\'un.e secrétaire, d\'un.e trésorier.ère. Il peut adjoindre au bureau un.e ou deux vice-président.e.s, un.e'
                    . ' secrétaire adjoint.e et un.e trésorier.ère adjoint.e. Il est recherché une représentation équilibrée entre les femmes'
                    . ' et les hommes au sein du bureau de l\'association.</p>'
                    . '<p>Le bureau du conseil d\'administration est élu après chaque renouvellement par moitié du conseil.</p>'
                    . '<h3>Article 7 : fonctionnement du conseil d\'administration</h3>'
                    . '<p>Le conseil d\'administration se réunit au moins une fois tous les six mois et chaque fois qu\'il est convoqué par son'
                    . ' président ou sur la demande de la majorité de ses membres ou du quart des membres de l\'association. La présence des'
                    . ' deux tiers au moins des membres du conseil d\'administration est nécessaire pour la validité des délibérations.</p>'
                    . '<p>En cas de partage des voix, celle du/de la président.e est prépondérante.</p>'
                    . '<p>Le conseil d\'administration délibère pour autoriser l\'introduction des actions juridiques correspondant à l\'objet'
                    . ' de l\'association.</p>'
                    . '<p>Le conseil d\'administration est chargé de l\'agrément des nouveaux membres. Il se fonde sur un entretien du/de la'
                    . ' postulant.e réalisé par un membre qu\'il désigne. Il peut définir une période probatoire pour l\'admission des'
                    . ' postulant.e.s. Les membres du conseil d\'administration ayant la qualité de membres fondateurs de l\'association'
                    . ' disposent d\'un droit de veto sur l\'admission des nouveaux membres.</p>'
                    . '<p>Tout membre du conseil d\'administration qui, sans excuse, n\'aura pas assisté à trois réunions consécutives, pourra'
                    . ' être considéré comme démissionnaire.</p>'
                    . '<p>Toute personne dont la présence lui paraît utile, en particulier, si tel est le cas, les agents salariés et'
                    . ' rétribués, non membres de l\'association, peut être invitée par le/la président.e à assister, avec voix consultative,'
                    . ' aux séances de l\'assemblée générale et du conseil d\'administration.</p>'
                    . '<p>Il est tenu procès-verbal des séances du conseil d\'administration. Les procès-verbaux sont signés par le/la'
                    . ' président.e et le/la secrétaire.</p>'
                    . '<h3>Article 8 : caractère bénévole des membres du conseil d\'administration</h3>'
                    . '<p>Les membres du conseil d\'administration ne peuvent recevoir aucune rétribution à raison des fonctions qui leur sont'
                    . ' confiées.</p>'
                    . '<p>Des remboursements de frais sont seuls possibles. Ils doivent faire l\'objet d\'une décision expresse du conseil'
                    . ' d\'administration, statuant hors de la présence des intéressés. Des justifications doivent être produites et font'
                    . ' l\'objet de vérifications.</p>'
                    . '<h3>Article 9 : assemblée générale</h3>'
                    . '<p>L\'assemblée générale de l\'association comprend les membres adhérents personnes physiques, les membres adhérents'
                    . ' personnes morales et les membres d\'honneur. Les membres adhérents personnes morales sont représentés par leur'
                    . ' représentant légal dûment mandaté ou par une personne désignée à cet effet.</p>'
                    . '<p>L\'assemblée générale se réunit au moins une fois par an et chaque fois qu\'elle est convoquée par le conseil'
                    . ' d\'administration ou sur la demande du quart au moins des membres de l\'association.</p>'
                    . '<p>Son ordre du jour est réglé par le conseil d\'administration.</p>'
                    . '<p>Sauf application des dispositions de l\'article 6, les agents salariés et rétribués, non membres de l\'association,'
                    . ' n\'ont pas accès à l\'assemblée générale.</p>'
                    . '<p>L\'assemblée générale de l\'association choisit son/sa président.e et son/sa secrétaire de séance qui peuvent être'
                    . ' ceux/celles siégeant au conseil d\'administration.</p>'
                    . '<p>Elle entend les rapports sur la gestion du conseil d\'administration, sur la situation financière et morale de'
                    . ' l\'association.</p>'
                    . '<p>Elle approuve les comptes de l\'exercice clos, vote le budget de l\'exercice suivant, délibère sur les questions'
                    . ' mises à l\'ordre du jour et pourvoit, s\'il y a lieu, au renouvellement des membres du conseil d\'administration. Pour'
                    . ' l\'exercice du vote, celui-ci peut être accompli par correspondance, par voie électronique ou par procuration. Dans ce'
                    . ' dernier cas, chaque membre présent ne peut détenir plus de trois pouvoirs en sus du sien.</p>'
                    . '<p>En cas de partage des voix, celle du/de la président.e est prépondérante.</p>'
                    . '<p>Il est tenu procès-verbal des séances. Les procès-verbaux sont signés par le/la président.e et le/la secrétaire de'
                    . ' séance.</p>'
                    . '<h3>Article 10 : présidence de l\'association</h3>'
                    . '<p>Le/La président.e représente l\'association dans tous les actes de la vie civile, notamment pour représenter'
                    . ' l\'association en justice. Il/elle ordonnance les dépenses. Il/elle peut donner délégation dans des conditions qui'
                    . ' sont fixées par le règlement intérieur.</p>'
                    . '<p>Concernant la représentation de l\'association en justice, le/la président.e ne peut être remplacé.e que par un.e'
                    . ' vice-président.e en cas d\'indisponibilité, et dans les autres cas, par un/une mandataire agissant en vertu d\'une'
                    . ' procuration spéciale.</p>'
                    . '<p>Les représentant.e.s de l\'association doivent jouir du plein exercice de leurs droits civils.</p>'
                    . '<h3>Article 11 : comités locaux et sections</h3>'
                    . '<p>Des comités locaux ou des sections peuvent être créés par délibération du conseil d\'administration approuvée par'
                    . ' l\'assemblée générale.</p>'
                    . '<p>Le comité local ou la section vise à faciliter l\'action d\'un groupe de membres ou à distinguer une activité de'
                    . ' l\'association. Elle ne dispose pas de la personnalité juridique.</p>'
                    . '<p>Le conseil d\'administration propose à l\'assemblée générale la délimitation, les attributions et les modalités de'
                    . ' fonctionnement du comité local ou de la section.</p>'
                    . '<p>L\'animateur/animatrice du comité local ou de la section est désigné.e par le conseil d\'administration sur'
                    . ' proposition du/de la président.e.</p>'
                    . '<h2>III. Dotation et ressources</h2>'
                    . '<h3>Article 12 : ressources de l\'association</h3>'
                    . '<p>Les recettes annuelles de l\'association se composent :</p>'
                    . '<ol>'
                    . '<li>des cotisations, dons, legs et souscriptions ;</li>'
                    . '<li>des subventions de l\'État, des collectivités territoriales, des établissements publics ;</li>'
                    . '<li>de mécénat ;</li>'
                    . '<li>des ressources créées à titre exceptionnel et, s\'il y a lieu, avec l\'agrément de l\'autorité compétente : quêtes,'
                    . ' souscription, tombolas, loteries, concerts, bals et spectacles, etc. ;</li>'
                    . '<li>du produit des ventes et des rétributions perçues pour service rendu et prestations de services (conférences,'
                    . ' cours, etc.).</li>'
                    . '</ol>'
                    . '<h3>Article 13 : comptabilité</h3>'
                    . '<p>Il est tenu une comptabilité faisant apparaître annuellement un compte de résultat et un bilan.</p>'
                    . '<p>Chaque comité local ou section de l\'association doit tenir une comptabilité distincte qui forme un chapitre spécial'
                    . ' de la comptabilité d\'ensemble de l\'association.</p>'
                    . '<h2>IV. Règlement intérieur</h2>'
                    . '<h3>Article 14 : adoption du règlement intérieur</h3>'
                    . '<p>Le règlement intérieur est adopté par l\'assemblée générale.</p>'
                    . '<h2>V. Modification des statuts et dissolution</h2>'
                    . '<h3>Article 15 : modification des statuts et dissolution de l\'association</h3>'
                    . '<p>Une assemblée générale extraordinaire doit être réunie afin de procéder à la modification des statuts ou à la'
                    . ' dissolution de l\'association.</p>'
                    . '<p>La réunion de cette assemblée générale extraordinaire est décidée par le conseil d\'administration réunissant les'
                    . ' trois quarts de ses membres ou sur la proposition de la moitié des membres dont se compose l\'assemblée générale.</p>'
                    . '<p>La proposition de modification des statuts ou de dissolution de l\'association est alors inscrite à l\'ordre du jour'
                    . ' d\'une assemblée générale extraordinaire convoquée à ce titre. L\'ordre du jour de cette assemblée générale'
                    . ' extraordinaire doit être adressé ou rendu accessible à tous les membres de l\'assemblée au moins 15 jours à l\'avance.</p>'
                    . '<p>Pour pouvoir valablement délibérer, l\'assemblée générale extraordinaire doit se composer du tiers au moins des'
                    . ' membres en exercice. Si cette proportion n\'est pas atteinte, l\'assemblée est convoquée de nouveau à 15 jours au moins'
                    . ' d\'intervalle, et, cette fois, elle peut valablement délibérer, quel que soit le nombre des membres présents ou'
                    . ' représentés.</p>'
                    . '<p>Dans tous les cas, les décisions de l\'assemblée générale extraordinaire ne peuvent être prises qu\'à la majorité des'
                    . ' deux tiers des membres présents ou représentés.</p>'
                    . '<p>En cas de dissolution de l\'association, l\'assemblée générale extraordinaire désigne un.e ou plusieurs commissaires,'
                    . ' chargé.e.s de la liquidation des biens de l\'association. Elle attribue l\'actif net à une ou plusieurs associations,'
                    . ' établissements ou institutions poursuivant un objet analogue. L\'actif ne pourra être attribué ni à un organisme ayant'
                    . ' un but lucratif, ni aux membres de l\'association, sauf reprise de leurs apports.</p>'
                    . '<h2>Identification de l\'association</h2>'
                    . '<ul>'
                    . '<li>Association déclarée (loi du 1er juillet 1901), immatriculée depuis le 14 avril 2025.</li>'
                    . '<li>Numéro RNA : W751278857.</li>'
                    . "<li>SIREN : 943\u{a0}896\u{a0}340 ; SIRET du siège : 943\u{a0}896\u{a0}340\u{a0}00016.</li>"
                    . '<li>Siège social : 19 avenue d\'Italie, 75013 Paris.</li>'
                    . '</ul>'
                    . '<p>Voir aussi la <a href="/charte">charte des principes d\'action</a> de l\'association.</p>';
                $statutsPage = Pages::findBySlug('statuts');
                if ($statutsPage !== null && $statutsPage['updated_by'] === 'contenu initial'
                    && strpos((string) $statutsPage['blocks'], 'Extraits des statuts') !== false) {
                    Db::update('pages', (int) $statutsPage['id'], [
                        'blocks' => json_encode([['type' => 'text', 'html' => Sanitizer::html($statuts)]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'seo_description' => 'Statuts de France État de Droit : objet, moyens d\'action, valeurs, composition, administration'
                            . ' et fonctionnement de l\'association.',
                        'updated_at' => now_iso(),
                    ]);
                    Pages::recordVersion((int) $statutsPage['id'], 'contenu initial', 'Statuts modifiés le 19 novembre 2025');
                }

                // Charter of action principles: a new page, right after the statutes in the menu.
                $charte = '<p>Approuvée par l\'assemblée générale constitutive du 6 avril 2025, la charte des principes d\'action'
                    . ' fixe la manière dont ' . $q . ' agit. Tout membre s\'engage à la respecter, comme les'
                    . ' <a href="/statuts">statuts</a>. En voici l\'essentiel.</p>'
                    . '<h2>Pourquoi une charte</h2>'
                    . '<ul>'
                    . '<li><strong>Préciser ce que nous apportons</strong>, à côté d\'autres associations citoyennes aux objets et aux valeurs'
                    . ' proches.</li>'
                    . '<li><strong>Fixer des règles d\'action</strong> pour que l\'association serve réellement l\'objet, les moyens et les'
                    . ' valeurs inscrits dans ses statuts.</li>'
                    . '<li><strong>Éviter les blocages internes</strong> entre des lectures différentes, et légitimes, de cet objet.</li>'
                    . '<li><strong>Éviter toute récupération</strong> par des personnes ou des mouvements qui détourneraient l\'association'
                    . ' de son objet.</li>'
                    . '<li><strong>Éviter la dispersion</strong>, notamment face aux annonces politiques faites surtout pour provoquer une'
                    . ' réaction et se faire de la publicité.</li>'
                    . '</ul>'
                    . '<h2>Nos principes d\'action</h2>';
                $principles = [
                    ['media_id' => null, 'url' => '', 'title' => 'Raisonner en droit',
                        'text' => 'Face à un acte litigieux, nous posons deux questions : quelle règle de droit s\'applique, et l\'acte la'
                            . ' méconnaît-il ? Nous ne jugeons ni ses auteurs, ni la justesse de l\'acte selon nos opinions.'],
                    ['media_id' => null, 'url' => '', 'title' => 'Préserver l\'équilibre du droit',
                        'text' => 'Les droits et libertés résultent d\'équilibres subtils entre des besoins contradictoires. Nous défendons'
                            . ' cet équilibre et laissons à d\'autres la création de droits nouveaux ou l\'extension des droits existants.'],
                    ['media_id' => null, 'url' => '', 'title' => 'Défendre les juges et les cours',
                        'text' => 'Nous veillons à l\'indépendance de la justice et au bon fonctionnement du Conseil constitutionnel,'
                            . ' du Conseil d\'État, de la Cour des comptes, de la Cour de cassation, de la CJUE et de la CEDH.'],
                    ['media_id' => null, 'url' => '', 'title' => 'Rester indépendants',
                        'text' => 'Nous ne promouvons aucun mouvement politique, idéologique ou religieux et n\'avons aucun lien en ce sens.'
                            . ' Notre action se situe dans l\'arc républicain, au soutien des valeurs humanistes et démocratiques.'],
                    ['media_id' => null, 'url' => '', 'title' => 'Choisir nos partenaires',
                        'text' => 'Nous ne nous associons à aucune personne ni aucun groupement qui, par ses actes ou ses déclarations,'
                            . ' ne promeut pas la non-violence, l\'absence de discriminations et le respect de l\'État de droit.'],
                    ['media_id' => null, 'url' => '', 'title' => 'Soutenir des personnes, sujets de droit',
                        'text' => 'Notre attention aux femmes, aux étrangers, aux personnes privées de liberté ou handicapées, et à'
                            . ' l\'orientation sexuelle, vise les personnes en tant que sujets de droit, pas leurs déclarations ou leurs actes.'],
                ];
                $charteEnd = '<h2>Rejoindre l\'association</h2>'
                    . '<p>Avant d\'accueillir un nouveau membre, l\'association s\'assure de son adhésion aux statuts, notamment aux valeurs'
                    . ' qui y sont inscrites, à son règlement intérieur et à ses chartes, et de son engagement à les respecter.</p>'
                    . '<p><a class="btn" href="/adherer">Adhérer à l\'association</a></p>';
                $statutsPage = Pages::findBySlug('statuts');
                if (!Pages::slugTaken('charte', null) && Db::value('SELECT page_id FROM redirects WHERE old_slug = ?', ['charte']) === null) {
                    $after = $statutsPage !== null && $statutsPage['parent_id'] === null
                        ? (int) $statutsPage['position']
                        : (int) Db::value('SELECT COALESCE(MAX(position), 0) FROM pages WHERE parent_id IS NULL');
                    Db::run('UPDATE pages SET position = position + 1 WHERE parent_id IS NULL AND position > ?', [$after]);
                    $now = now_iso();
                    $id = Db::insert('pages', [
                        'parent_id' => null,
                        'position' => $after + 1,
                        'title' => 'Notre charte',
                        'slug' => 'charte',
                        'status' => 'published',
                        'show_in_menu' => 1,
                        'show_in_footer' => 0,
                        'is_home' => 0,
                        'seo_title' => 'Charte des principes d\'action',
                        'seo_description' => 'La charte des principes d\'action de France État de Droit : raisonner en droit, rester'
                            . ' indépendants, défendre l\'équilibre du droit et l\'indépendance de la justice.',
                        'blocks' => json_encode([
                            ['type' => 'text', 'html' => Sanitizer::html($charte)],
                            ['type' => 'cards', 'cards' => $principles],
                            ['type' => 'text', 'html' => Sanitizer::html($charteEnd)],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'created_at' => $now,
                        'updated_at' => $now,
                        'updated_by' => 'contenu initial',
                    ]);
                    Pages::recordVersion($id, 'contenu initial', 'Charte des principes d\'action');
                }

                // "Adhérer": link the charter and give the fees from article 4 of the statutes.
                $adherer = Pages::findBySlug('adherer');
                $old = 'la charte des principes d\'action de l\'association';
                if ($adherer !== null && $adherer['updated_by'] === 'contenu initial') {
                    $blocks = Pages::blocks($adherer);
                    $html = (string) ($blocks[0]['html'] ?? '');
                    if (count($blocks) === 1 && strpos($html, $old) !== false && strpos($html, 'Cotisation annuelle') === false) {
                        $html = str_replace($old, 'la <a href="/charte">charte des principes d\'action</a> de l\'association', $html);
                        $fees = '<h2>Cotisation annuelle</h2>'
                            . '<ul>'
                            . '<li>20 € pour les personnes de 30 ans et plus ;</li>'
                            . '<li>10 € pour les moins de 30 ans et les bénéficiaires des minima sociaux ;</li>'
                            . '<li>100 € pour les personnes morales (associations, organismes).</li>'
                            . '</ul>'
                            . '<p>Chaque demande d\'adhésion est agréée par le conseil d\'administration, après un entretien avec un membre'
                            . ' de l\'association.</p>';
                        $html = str_replace('<h2>Adhérer en ligne</h2>', $fees . '<h2>Adhérer en ligne</h2>', $html);
                        $blocks[0]['html'] = Sanitizer::html($html);
                        Db::update('pages', (int) $adherer['id'], [
                            'blocks' => json_encode($blocks, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now_iso(),
                        ]);
                        Pages::recordVersion((int) $adherer['id'], 'contenu initial', 'Cotisations et lien vers la charte');
                    }
                }

                // Footer: the official identifiers, unless an editor changed the footer text.
                $footer = Db::value("SELECT value FROM settings WHERE key = 'footer_text'");
                if ($footer === null || $footer === '' || $footer === 'Siège social : 19 avenue d\'Italie, 75013 Paris.') {
                    // Non-breaking spaces keep the SIRET digit groups on one line.
                    Settings::set('footer_text', "Association déclarée (loi 1901).\n"
                        . "RNA W751278857 · SIRET 943\u{a0}896\u{a0}340\u{a0}00016.\n"
                        . 'Siège social : 19 avenue d\'Italie, 75013 Paris.');
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
