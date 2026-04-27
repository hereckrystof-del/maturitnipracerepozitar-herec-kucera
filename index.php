<?php
session_start(['cookie_lifetime' => 0]); 

//HESLO PRO VSTUP
$archiv_heslo = 'maturita';

if (isset($_POST['archiv_heslo_submit'])) {
    if ($_POST['archiv_heslo'] === $archiv_heslo) {
        $_SESSION['archiv_pristup'] = true;
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    } else {
        $error_msg = "Nesprávné heslo.";
    }
}

if (empty($_SESSION['archiv_pristup'])) {
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Neoficiální archiv - Vyžadováno heslo</title>
        <link rel="stylesheet" href="css/style.css">
    </head>
    <body class="archive-login-body">
        <div class="archive-login-box">
            <span class="archive-warning-badge">Maturitní verze</span>
            <h1 class="archive-title">Archiv webu TK Rozrazil</h1>
            <p class="archive-text">Tato stránka již není oficiální stránkou oddílu. Pro vstup zadejte heslo.</p>
            
            <?php if (!empty($error_msg)): ?>
                <div class="archive-error-msg"><?php echo htmlspecialchars($error_msg); ?></div>
            <?php endif; ?>
            
            <form method="post" class="archive-form">
                <input type="password" name="archiv_heslo" class="archive-input" placeholder="Zadejte heslo" required autofocus>
                <button type="submit" name="archiv_heslo_submit" class="archive-btn">Vstoupit</button>
            </form>

            <div class="archive-official-link">
                
                Přejděte na <a href="https://tkrozrazil.cz" target="_blank">oficiální web TK Rozrazil</a>.
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}
//KONEC HESLA PRO VSTUP

$timeout = 600; //10 minut

if (isset($_SESSION['user'])) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();     
        session_destroy();   
        header("Location: /"); 
        exit;
    }
    $_SESSION['last_activity'] = time(); 
}

// router - ciste URL
$url = isset($_GET['url']) ? rtrim($_GET['url'], '/') : 'aktuality';
$activeTab = 'aktuality';

// casti URL pro modaly
$url_parts = explode('/', $url);
$base_url = !empty($url_parts[0]) ? $url_parts[0] : 'aktuality';
$open_item_id = isset($url_parts[1]) ? (int)$url_parts[1] : null;

switch ($base_url) {
    case 'aktuality':
    case '': 
        $activeTab = 'aktuality';
        break;
    case 'o-nas':
        $activeTab = 'onas';
        break;
    case 'akce':
        $activeTab = 'akce';
        break;
    case 'schuzky':
        $activeTab = 'schuzky';
        break;
    case 'letni-tabor':
        $activeTab = 'tabor';
        break;
    case 'fotogalerie':
        $activeTab = 'fotogalerie';
        break;
    case 'kontakt':
        $activeTab = 'kontakt';
        break;
    default:
        $activeTab = 'aktuality'; 
        break;
}

//bezpecne nacteni udaju od databaze
require_once __DIR__ . '/../config/config.php';

//kontrola jestli uzivatel existuje
if (isset($_SESSION['user']) && $conn) {
    $stmt_check_user = $conn->prepare("SELECT login FROM uzivatele WHERE login = ?");
    $stmt_check_user->bind_param("s", $_SESSION['user']);
    $stmt_check_user->execute();
    $res_check_user = $stmt_check_user->get_result();
    
    //kdyz neextistuje
    if ($res_check_user->num_rows === 0) {
        session_unset();
        session_destroy();
        header("Location: /");
        exit;
    }
    $stmt_check_user->close();
}

//open graph meta
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$domain = $protocol . "://" . $_SERVER['HTTP_HOST'];

//vychozi hodnoty
$meta_title = "TK Rozrazil Brno";
$meta_desc = "Tábornický oddíl TK Rozrazil Brno. Pořádáme letní tábory, víkendové výpravy a pravidelné schůzky pro děti. Přidejte se k nám!";
$meta_image = $domain . "/img/logo.png";
$meta_url = $domain . $_SERVER['REQUEST_URI'];

if ($open_item_id && $conn) {
    if ($base_url === 'aktuality') {
        $stmt_meta = $conn->prepare("SELECT title, content, image FROM aktuality WHERE id = ?");
        $stmt_meta->bind_param("i", $open_item_id);
        $stmt_meta->execute();
        $res_meta = $stmt_meta->get_result();
        if ($row_meta = $res_meta->fetch_assoc()) {
            $meta_title = $row_meta['title'] . " | TK Rozrazil";
            $meta_desc = mb_substr(strip_tags($row_meta['content']), 0, 150) . '...';
            if (!empty($row_meta['image'])) {
                $meta_image = $domain . "/uploads/Aktuality/" . rawurlencode($row_meta['image']);
            }
        }
        $stmt_meta->close();
    } elseif ($base_url === 'akce') {
        $stmt_meta = $conn->prepare("SELECT title, content, image, datum, rok FROM posts WHERE id = ?");
        $stmt_meta->bind_param("i", $open_item_id);
        $stmt_meta->execute();
        $res_meta = $stmt_meta->get_result();
        if ($row_meta = $res_meta->fetch_assoc()) {
            $meta_title = $row_meta['title'] . " | TK Rozrazil";
            $meta_desc = mb_substr(strip_tags($row_meta['content']), 0, 150) . '...';
            if (!empty($row_meta['image'])) {
                $rok = $row_meta['rok'] ?? date('Y', strtotime($row_meta['datum']));
                $meta_image = $domain . "/uploads/Akce/" . $rok . "/" . rawurlencode($row_meta['image']);
            }
        }
        $stmt_meta->close();
    }
}


const ITEMS_PER_PAGE = 8; //pocet polozek

//bannery
$banery = [];
if ($conn) {
    $checkBaner = $conn->query("SHOW TABLES LIKE 'baner'");
    if ($checkBaner && $checkBaner->num_rows > 0) {
        $resBaner = $conn->query("SELECT id, image, content FROM baner ORDER BY id DESC");
        if ($resBaner && $resBaner->num_rows > 0) {
            while ($b = $resBaner->fetch_assoc()) {
                $banery[] = $b;
            }
        }
    }
}
$showBanery = !empty($banery) || isset($_SESSION['user']);

//aktuality a strankovani
$aktuality        = [];
$aktuality_total  = 0;
$page_aktuality   = max(1, intval($_GET['page_aktuality'] ?? 1));
$offset_aktuality = ($page_aktuality - 1) * ITEMS_PER_PAGE;

if ($conn) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'aktuality'");
    if ($checkTable && $checkTable->num_rows > 0) {
        $res_count = $conn->query("SELECT COUNT(*) AS cnt FROM aktuality");
        if ($res_count) $aktuality_total = (int)$res_count->fetch_assoc()['cnt'];

        $stmt = $conn->prepare("SELECT id, title, datum, datum_do, content, odkaz, autor FROM aktuality ORDER BY datum DESC LIMIT ? OFFSET ?");
        $limit = ITEMS_PER_PAGE;
        $stmt->bind_param("ii", $limit, $offset_aktuality);
        $stmt->execute();
        $res = $stmt->get_result();
        
        $aktuality_temp = [];
        $news_ids = [];
        
        while ($row = $res->fetch_assoc()) {
            $row['images'] = []; //pro nove obr
            $aktuality_temp[$row['id']] = $row;
            $news_ids[] = $row['id'];
        }
        $stmt->close();

        if (!empty($news_ids)) {
            $ids_str = implode(',', array_map('intval', $news_ids));
            $img_res = $conn->query("SELECT news_id, image_path FROM news_images WHERE news_id IN ($ids_str)");
            
            if ($img_res) {
                while ($img = $img_res->fetch_assoc()) {
                    $nid = $img['news_id'];
                    $aktuality_temp[$nid]['images'][] = 'uploads/Aktuality/' . rawurlencode($img['image_path']);
                }
            }
        }
        
        $aktuality = array_values($aktuality_temp);
    }
}
$aktuality_pages = $aktuality_total > 0 ? (int)ceil($aktuality_total / ITEMS_PER_PAGE) : 1;

//akce a strankovani
$akce        = [];
$akce_total  = 0;
$page_akce   = max(1, intval($_GET['page_akce'] ?? 1));
$offset_akce = ($page_akce - 1) * ITEMS_PER_PAGE;

// filtr podle roku
$akce_roky = [];
$filter_rok = isset($_GET['rok_akce']) && is_numeric($_GET['rok_akce']) ? (int)$_GET['rok_akce'] : 0;

if ($conn) {
    $checkTable = $conn->query("SHOW TABLES LIKE 'posts'");
    if ($checkTable && $checkTable->num_rows > 0) {
        // roky ve kterych neco je
        $resRoky = $conn->query("SELECT DISTINCT YEAR(datum) AS rok FROM posts ORDER BY rok DESC");
        if ($resRoky) {
            while ($r = $resRoky->fetch_assoc()) {
                $akce_roky[] = (int)$r['rok'];
            }
        }

        // kdyz vybrany rok neni v databazi, reset
        if ($filter_rok && !in_array($filter_rok, $akce_roky)) {
            $filter_rok = 0;
        }

        if ($filter_rok) {
            $count_stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM posts WHERE YEAR(datum) = ?");
            $count_stmt->bind_param("i", $filter_rok);
            $count_stmt->execute();
            $akce_total = (int)$count_stmt->get_result()->fetch_assoc()['cnt'];
            $count_stmt->close();

            $stmt = $conn->prepare("SELECT id, title, datum, datum_do, content, odkaz, rok, created, created_at, autor FROM posts WHERE YEAR(datum) = ? ORDER BY datum DESC LIMIT ? OFFSET ?");
            $limit = ITEMS_PER_PAGE;
            $stmt->bind_param("iii", $filter_rok, $limit, $offset_akce);
        } else {
            $res_count = $conn->query("SELECT COUNT(*) AS cnt FROM posts");
            if ($res_count) $akce_total = (int)$res_count->fetch_assoc()['cnt'];

            $stmt = $conn->prepare("SELECT id, title, datum, datum_do, content, odkaz, rok, created, created_at, autor FROM posts ORDER BY datum DESC LIMIT ? OFFSET ?");
            $limit = ITEMS_PER_PAGE;
            $stmt->bind_param("ii", $limit, $offset_akce);
        }

        $stmt->execute();
        $res = $stmt->get_result();
        
        $akce_temp = [];
        $post_ids = [];
        
        while ($row = $res->fetch_assoc()) {
            $rok = $row['rok'] ?? date('Y', strtotime($row['datum']));
            $row['images'] = [];
            $row['slozka_rok'] = $rok;
            
            $akce_temp[$row['id']] = $row;
            $post_ids[] = $row['id'];
        }
        $stmt->close();

        //jestli se nacetli nejake akce, pridame k nim obr z tabulky
        if (!empty($post_ids)) {
            $ids_str = implode(',', array_map('intval', $post_ids));
            $img_res = $conn->query("SELECT post_id, image_path FROM post_images WHERE post_id IN ($ids_str)");
            
            if ($img_res) {
                while ($img = $img_res->fetch_assoc()) {
                    $pid = $img['post_id'];
                    $rok = $akce_temp[$pid]['slozka_rok'];
                    $akce_temp[$pid]['images'][] = 'uploads/Akce/' . $rok . '/' . rawurlencode($img['image_path']);
                }
            }
        }
        
        $akce = array_values($akce_temp);
    }
}
$akce_pages = $akce_total > 0 ? (int)ceil($akce_total / ITEMS_PER_PAGE) : 1;

//verze souboru
$css_ver = file_exists(__DIR__ . '/css/style.css') ? filemtime(__DIR__ . '/css/style.css') : time();
$js_ver  = file_exists(__DIR__ . '/javascript/main.js') ? filemtime(__DIR__ . '/javascript/main.js') : time();

// prevod textu na odkaz
function render_content(string $text): string {
    $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    $escaped = preg_replace_callback(
        '/\[([^\]]{1,200})\]\((https?:\/\/[^\)]{1,500})\)/',
        function(array $m): string {
            $label = $m[1];
            $url   = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer" class="content-link">' . $label . '</a>';
        },
        $escaped
    );
    return nl2br($escaped);
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5, user-scalable=yes">
  <base href="/">
  
  <title><?php echo htmlspecialchars($meta_title); ?></title>
  <meta name="description" content="<?php echo htmlspecialchars($meta_desc); ?>">
  <meta name="keywords" content="TK Rozrazil, tábornický oddíl Brno, dětský tábor, volný čas děti Brno, ČTU, tábornický klub">
  <meta name="author" content="TK Rozrazil">

  <meta property="og:type" content="website">
  <meta property="og:url" content="<?php echo htmlspecialchars($meta_url); ?>">
  <meta property="og:title" content="<?php echo htmlspecialchars($meta_title); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($meta_desc); ?>">
  <meta property="og:image" content="<?php echo htmlspecialchars($meta_image); ?>">

  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:url" content="<?php echo htmlspecialchars($meta_url); ?>">
  <meta property="twitter:title" content="<?php echo htmlspecialchars($meta_title); ?>">
  <meta property="twitter:description" content="<?php echo htmlspecialchars($meta_desc); ?>">
  <meta property="twitter:image" content="<?php echo htmlspecialchars($meta_image); ?>">
  
  <link rel="preload" as="image" href="img/hero-bg.webp">
  <link rel="icon" type="image/png" href="img/favicon.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/3.5.0/remixicon.css">
  <link rel="stylesheet" href="css/style.css?v=<?php echo $css_ver; ?>">
  <?php if (!isset($_SESSION['user'])): ?>
  <script src="https://www.google.com/recaptcha/api.js" async defer></script>
  <?php endif; ?>
</head>
<body>

<header class="header" id="header">
    <div class="header-left">
      <a href="/aktuality" class="brand-container">
        <img src="img/logo.png" alt="Logo" class="brand-logo">
        <span class="brand-text">TK Rozrazil</span>
      </a>
      
      <nav class="nav" id="nav-menu">
        <ul class="nav-list">
          <li><a href="/o-nas" class="nav-link <?php echo ($activeTab === 'onas') ? 'active' : ''; ?>">O nás</a></li>
          <li><a href="/akce" class="nav-link <?php echo ($activeTab === 'akce') ? 'active' : ''; ?>">Akce</a></li>
          <li><a href="/schuzky" class="nav-link <?php echo ($activeTab === 'schuzky') ? 'active' : ''; ?>">Schůzky</a></li>
          <li><a href="/letni-tabor" class="nav-link <?php echo ($activeTab === 'tabor') ? 'active' : ''; ?>">Tábor</a></li>
          <li><a href="/fotogalerie" class="nav-link <?php echo ($activeTab === 'fotogalerie') ? 'active' : ''; ?>">Fotogalerie</a></li>
          <li><a href="/kontakt" class="nav-link <?php echo ($activeTab === 'kontakt') ? 'active' : ''; ?>">Kontakt</a></li>
        </ul>

        <div class="nav-close" id="nav-close">
          <i class="ri-close-line"></i>
        </div>
      </nav>
    </div>

    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-GM48RYC00R"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', 'G-GM48RYC00R');
    </script>

    <div class="header-right">
      <?php if(isset($_SESSION['user'])): ?>
        <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
          <button class="settings-btn" data-modal-target="settingsModal" title="Správa uživatelů" aria-label="Správa uživatelů">⚙</button>
        <?php endif; ?>
        <span class="user-info">
            Přihlášen: <strong><?php echo htmlspecialchars($_SESSION['user']); ?></strong>
        </span>
        <a href="logout.php" class="logout-btn">Odhlásit</a>
      <?php else: ?>
        <button class="header-login" data-modal-target="loginModal">Přihlásit</button>
      <?php endif; ?>

      <div class="nav-toggle" id="nav-toggle">
        <i class="ri-menu-line"></i>
      </div>
    </div>
  </header>

  <button id="floatingCalendarBtn" data-modal-target="calendarModal" title="Kalendář akcí" aria-label="Otevřít kalendář">
    <i class="ri-calendar-event-line"></i>
  </button>

  <section class="hero-fullscreen <?php echo ($activeTab !== 'aktuality') ? 'hidden' : ''; ?>" id="hero-section" aria-label="Úvodní sekce">
    <div class="hero-overlay-layer" aria-hidden="true"></div>
    <div class="hero-overlay-vignette" aria-hidden="true"></div>
    <div class="hero-content-container">
      <h1 class="hero-title-large">
        <span class="hero-title-line">Objevte kouzlo </span>
        <span class="hero-title-line hero-title-accent">táborových ohňů</span>
      </h1>
      <p class="hero-subtitle-large">
        Tradice, přátelství a dobrodružství v přírodě. Přidejte se k nám a zažijte schůzky, víkendové výpravy i letní tábor, na který se nezapomíná.
      </p>
      <div class="hero-cta-buttons">
        <a href="/o-nas" class="btn-hero btn-hero-primary">
          <span>Náš příběh</span>
          <i class="ri-arrow-right-line" aria-hidden="true"></i>
        </a>
        <a href="/letni-tabor" class="btn-hero btn-hero-ghost">
          <span>Letní tábor</span>
        </a>
      </div>
    </div>
    <button class="hero-scroll-btn" onclick="scrollToTabs()" aria-label="Přejít na obsah stránky">
      <span class="hero-scroll-arrow">
        <i class="ri-arrow-down-s-line" aria-hidden="true"></i>
      </span>
    </button>
  </section>

  <main class="container <?php echo ($activeTab !== 'aktuality') ? 'no-hero' : ''; ?>">
    
    <section id="aktuality" class="tab-content <?php echo ($activeTab === 'aktuality') ? 'active' : ''; ?>">
      
      <?php if ($showBanery): ?>
      <div class="banners-in-tab">
        <div class="section-header-row">
          <h3 style="margin-bottom: 0;">Bannery &amp; Plakáty</h3>
          <?php if (isset($_SESSION['user'])): ?>
            <button class="btn primary btn-add-new btn-static" data-modal-target="banerModal">+ Přidat banner</button>
          <?php endif; ?>
        </div>
        
        <?php if (empty($banery) && isset($_SESSION['user'])): ?>
          <p class="empty-state-text">
            Zatím zde nejsou žádné bannery. Přidejte první kliknutím na tlačítko výše.
          </p>
        <?php endif; ?>

        <div class="banery-grid-large">
          <?php foreach ($banery as $baner): ?>
            <?php
              $imgSrc = !empty($baner['image']) ? 'uploads/Banery/' . rawurlencode($baner['image']) : '';
              $baner_json = htmlspecialchars(json_encode($baner), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="banner-card-large">
              
              <div class="banner-card-header">
                <div class="banner-header-left">
                  <div class="banner-avatar">
                    <img src="img/logo.png" alt="TK Rozrazil">
                  </div>
                  <div class="banner-username">tkrozrazil</div>
                </div>

                <?php if (isset($_SESSION['user'])): ?>
                  <div class="banner-admin-actions-top">
                    <button class="btn-icon-sm" onclick='openEditBanerModal(<?php echo $baner_json; ?>)' title="Upravit">
                      <i class="ri-pencil-line"></i>
                    </button>
                    <button type="button" class="btn-icon-sm danger js-unified-delete" 
                       data-title="Smazat banner" 
                       data-msg="Opravdu chcete smazat tento banner? Tato akce je nevratná." 
                       data-url="actions/delete_baner.php?id=<?php echo $baner['id']; ?>" title="Smazat">
                      <i class="ri-delete-bin-line"></i>
                    </button>
                  </div>
                <?php endif; ?>
              </div>

              <?php if (!empty($imgSrc)): ?>
                <div class="image-zoom-wrapper" onclick="openSingleLightbox('<?php echo $imgSrc; ?>')">
                  <img src="<?php echo $imgSrc; ?>" alt="Banner" class="banner-image" loading="lazy">
                </div>
              <?php endif; ?>

              <div class="banner-card-body">
                <p class="instagram-caption">
                <strong>tkrozrazil</strong> <?php echo nl2br(htmlspecialchars($baner['content'])); ?>
              </p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="section-header-row">
        <h3 style="margin-bottom: 0;">Aktuality</h3>
  
        <div class="akce-actions-right">
          <?php if(isset($_SESSION['user'])): ?>
            <button class="btn primary btn-add-new btn-static" data-modal-target="newsModal">+ Přidat aktualitu</button>
          <?php endif; ?>
        </div>
      </div>

      <div class="posts-grid">
        <?php if (!empty($aktuality)): ?>
          <?php foreach ($aktuality as $post): ?>
            <?php
              $datum_format = date('d. m. Y', strtotime($post['datum']));
              if (!empty($post['datum_do'])) {
                  $datum_format .= ' – ' . date('d. m. Y', strtotime($post['datum_do']));
              }
              $post_json = htmlspecialchars(json_encode($post), ENT_QUOTES, 'UTF-8');
              $excerpt = mb_substr($post['content'], 0, 150) . (mb_strlen($post['content']) > 150 ? '...' : '');
            ?>
            <div class="post-card js-open-detail" data-id="<?php echo $post['id']; ?>" data-post='<?php echo $post_json; ?>'>
              <?php if (isset($_SESSION['user'])): ?>
                <div class="post-actions-overlay">
                  <button type="button" class="btn primary post-action-btn js-edit-news" data-post='<?php echo $post_json; ?>' title="Upravit">✎</button>
                  <button type="button" class="btn danger post-action-btn js-unified-delete" 
                          data-title="Smazat aktualitu" 
                          data-msg="Opravdu chcete smazat tuto aktualitu?" 
                          data-url="actions/delete_news.php?id=<?php echo $post['id']; ?>" title="Smazat">🗑</button>
                </div>
              <?php endif; ?>
              <small class="post-date"><?php echo $datum_format; ?></small>
              <h4 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h4>
              <p class="post-excerpt"><?php echo render_content($excerpt); ?></p>
              <?php if (!empty($post['odkaz'])): ?>
                <a href="<?php echo htmlspecialchars($post['odkaz']); ?>" target="_blank" class="btn ghost post-more-btn">Více informací</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Zatím zde nejsou žádné aktuality.</p>
        <?php endif; ?>
      </div>

      <?php if ($aktuality_pages > 1): ?>
        <nav class="pagination" aria-label="Stránkování aktualit">
          <?php for ($p = 1; $p <= $aktuality_pages; $p++): ?>
            <a href="/aktuality?page_aktuality=<?php echo $p; ?>"
               class="page-link <?php echo ($p === $page_aktuality) ? 'active' : ''; ?>"
               aria-current="<?php echo ($p === $page_aktuality) ? 'page' : 'false'; ?>">
              <?php echo $p; ?>
            </a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </section>

    <section id="onas" class="tab-content <?php echo ($activeTab === 'onas') ? 'active' : ''; ?>">
      <h3>TK Rozrazil: Tradice, příroda a přátelství, které trvá generace</h3>
      <p><strong>Hledáte pro své děti víc než jen kroužek?</strong> Objevte svět TK Rozrazil – brněnského tábornického klubu s hlubokými kořeny a srdcem na pravém místě.</p>

      <div class="section-block">
        <strong>Kde se píší legendy</strong>
        <p class="content-p">Náš příběh se začal psát v emotivní době krátce po Sametové revoluci. <strong>17. března 1990</strong> byl v magických prostorách „Jeskyně Úmluvy" založen Přírodovědně tábornický oddíl. Po roce samostatné cesty jsme se jako druhý tábornický klub v Brně stali hrdou součástí České tábornické unie pod názvem <strong>TK Rozrazil</strong>.</p>
      </div>

      <div class="section-block">
        <strong>Hodnoty, které formují charakter</strong>
        <p class="content-p">Věříme, že nejlepší učebnou je příroda sama. Naše činnost stojí na pevných základech:</p>
        <ul class="content-ul">
          <li><strong>Odkaz Jaroslava Foglara:</strong> Vedeme děti k čestnosti, samostatnosti a lásce k přírodě.</li>
          <li><strong>Tábornické dovednosti:</strong> Učíme děti, jak si poradit v lese i v životě.</li>
          <li><strong>Empatie a pohoda:</strong> Naší absolutní prioritou je, aby se u nás každé dítě cítilo bezpečně a dobře.</li>
        </ul>
      </div>

      <div class="section-block">
        <strong>Rok plný dobrodružství</strong>
        <p class="content-p">S námi děti nesedí doma. Naše celoroční činnost je nabitá zážitky:</p>
        <ul class="content-ul">
          <li>Pravidelné schůzky a víkendové výpravy</li>
          <li>Expedice do hor, kde překonáváme výzvy</li>
          <li>Letní tábory v údolí Chvojnice a Oslavy: Naše největší tradice, kterou držíme nepřetržitě už od roku 1977</li>
          <li>Kreativita a hry: Jsme mistři tematických her a v našich řadách najdete spoustu skvělých hudebníků</li>
        </ul>
      </div>

      <div class="section-block">
        <strong>Tradice, která zavazuje</strong>
        <p class="content-p"><strong>TK Rozrazil</strong> je víc než jen organizace – je to <strong>rodina</strong>.</p>
        <ul class="content-ul">
          <li><strong>Zkušenost a stabilita:</strong> Za roky naší existence se v čele klubu vystřídalo 7 náčelníků a každoročně pečujeme o 20 až 80 dětí</li>
          <li><strong>Nová generace:</strong> Své rádce a vedoucí si vychováváme sami. O síle našich tradic svědčí i to, že dnešní vedoucí jsou často dětmi zakládajících členů klubu</li>
          <li><strong>Nové útočiště:</strong> Po letech v centru Brna jsme v roce 2010 našli svůj nový domov v brněnských Medlánkách</li>
        </ul>
        <p class="content-p">Pomáháme také oživovat veřejný život v Brně akcemi jako Den dětí nebo setkáním tábornických klubů„Něco mezi".</p>
      </div>

      <p class="content-highlight-end">TK Rozrazil – s úctou k tradici, s nadšením pro budoucnost</p>
    </section>

    <section id="akce" class="tab-content <?php echo ($activeTab === 'akce') ? 'active' : ''; ?>">
  <h3>Proběhlé akce</h3>
  
  <div class="interactive-map-wrapper">
    <iframe src="https://www.google.com/maps/d/u/0/embed?mid=1E9E4gwLBVuzmuAsCoF6q52xBgKLC5CM&ehbc=2E312F&noprof=1" width="100%" height="100%"></iframe>
  </div>

  <div class="map-legend">
    <div class="legend-item">
      <i class="ri-map-pin-fill legend-icon icon-akce"></i>
      <span>Akce</span>
    </div>
    <div class="legend-item">
      <i class="ri-map-pin-fill legend-icon icon-tabor"></i>
      <span>Tábor</span>
    </div>
    <div class="legend-item">
      <i class="ri-home-4-fill legend-icon icon-klubovna"></i>
      <span>Klubovna</span>
    </div>
    <div class="legend-item">
      <i class="ri-walk-fill legend-icon icon-vypravy"></i>
      <span>Výpravy</span>
    </div>
  </div>
      
  <div class="akce-controls-row">
  <?php if (!empty($akce_roky)): ?>
  <div class="akce-year-filter">
    <label for="akce-rok-select"><i class="ri-calendar-line"></i> Filtr roku:</label>
    <select id="akce-rok-select" class="akce-year-select" onchange="filterAkceByRok(this.value)">
      <option value="0" <?php echo ($filter_rok === 0) ? 'selected' : ''; ?>>Všechny roky</option>
      <?php foreach ($akce_roky as $r): ?>
        <option value="<?php echo $r; ?>" <?php echo ($filter_rok === $r) ? 'selected' : ''; ?>>
          <?php echo $r; ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <?php else: ?>
    <div></div> 
  <?php endif; ?>

  <div class="akce-actions-right">
  <?php if(isset($_SESSION['user'])): ?>
    <button class="btn primary btn-add-new btn-static" data-modal-target="postModal">
      <i class="ri-add-line"></i> Přidat akci
    </button>
  <?php endif; ?>
</div>
</div>

      <div class="posts-grid">
        <?php if (!empty($akce)): ?>
          <?php foreach ($akce as $post): ?>
            <?php
              $datum_akce_display = date('d. m. Y', strtotime($post['datum']));
              if (!empty($post['datum_do'])) {
                  $datum_akce_display .= ' – ' . date('d. m. Y', strtotime($post['datum_do']));
              }
              $created_date    = $post['created'] ?? $post['created_at'] ?? '';
              $created_display = !empty($created_date) ? date('d. m. Y H:i', strtotime($created_date)) : '';
              $post_json = htmlspecialchars(json_encode($post), ENT_QUOTES, 'UTF-8');
              $excerpt   = mb_substr($post['content'], 0, 150) . (mb_strlen($post['content']) > 150 ? '...' : '');
            ?>
            <div class="post-card js-open-detail" data-id="<?php echo $post['id']; ?>" data-post='<?php echo $post_json; ?>'>
              <?php if (isset($_SESSION['user'])): ?>
                <div class="post-actions-overlay">
                  <button type="button" class="btn primary post-action-btn js-edit-post" data-post='<?php echo $post_json; ?>' title="Upravit">✎</button>
                  <button type="button" class="btn danger post-action-btn js-unified-delete" 
                          data-title="Smazat akci" 
                          data-msg="Opravdu chcete smazat tuto akci?" 
                          data-url="actions/delete_post.php?id=<?php echo $post['id']; ?>" title="Smazat">🗑</button>
                </div>
              <?php endif; ?>
              <?php if ($created_display): ?>
                <small class="post-date-added">Přidáno: <?php echo $created_display; ?></small>
              <?php endif; ?>
              <div class="post-date">Termín: <?php echo $datum_akce_display; ?></div>
              <h4 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h4>
              <p class="post-excerpt"><?php echo render_content($excerpt); ?></p>
              <?php if (!empty($post['odkaz'])): ?>
                <a href="<?php echo htmlspecialchars($post['odkaz']); ?>" target="_blank" class="btn ghost post-more-btn">Více informací</a>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p>Momentálně nejsou naplánovány žádné akce.</p>
        <?php endif; ?>
      </div>

      <?php if ($akce_pages > 1): ?>
        <nav class="pagination" aria-label="Stránkování akcí">
          <?php for ($p = 1; $p <= $akce_pages; $p++): ?>
            <?php $rok_param = $filter_rok ? '&rok_akce=' . $filter_rok : ''; ?>
            <a href="/akce?page_akce=<?php echo $p; ?><?php echo $rok_param; ?>"
               class="page-link <?php echo ($p === $page_akce) ? 'active' : ''; ?>"
               aria-current="<?php echo ($p === $page_akce) ? 'page' : 'false'; ?>">
              <?php echo $p; ?>
            </a>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </section>

    <section id="schuzky" class="tab-content <?php echo ($activeTab === 'schuzky') ? 'active' : ''; ?>">
      <h3>Pravidelné schůzky</h3>
      <p><strong>Chcete, aby vaše děti trávily odpoledne smysluplně, v pohybu a mezi kamarády?</strong> Naše družinové schůzky jsou místem, kde se učíme novým věcem, hrajeme hry a plánujeme další společná dobrodružství.</p>

      <div class="section-block">
        <strong>Kdy se potkáváme?</strong>
        <p class="content-p">Schůzky mladší družinky probíhají <strong>dvakrát týdně</strong>, aby si každý našel svůj čas:</p>
        <ul class="content-ul">
          <li><strong>Pondělí:</strong> 17:00 – 18:30</li>
          <li><strong>Čtvrtek:</strong> 17:00 – 18:30</li>
        </ul>
        <p class="content-p">Děti se mohou těšit na naše zkušené vedoucí, kterými jsou <strong>Rybák a Davča</strong>.</p>
      </div>

      <div class="section-block">
        <div class="address-map-container">
            <div class="address-text">
                <strong>Kde nás najdete?</strong>
                <p class="content-p">Zázemí máme v příjemném prostředí <strong>CVČ Jabloňka</strong> na adrese:</p>
                <p class="address-street">Jabloňová 11, Brno-Medlánky</p>
            </div>
            <div class="address-map">
                <iframe src="https://mapy.cz/s/fozogudoge" width="700" height="466" frameborder="0" loading="lazy"></iframe>
            </div>
        </div>
      </div>

      <div class="section-block">
        <strong>Výbava správného táborníka</strong>
        <p class="content-p">Aby si děti schůzku užily naplno, doporučujeme mít v batůžku tuto „tábornickou KPZ":</p>
        <ul class="content-ul">
          <li><strong>Deňoch (blok) a tužku</strong> – na poznámky, nápady a šifry</li>
          <li><strong>Uzlovačku</strong> – nejlépe 1,5 m dlouhé lano o průměru 1 cm (základ pro každého uzlaře!)</li>
          <li><strong>Čtyřcípý šátek</strong> – nepostradatelný pomocník při hrách i v terénu</li>
          <li><strong>Přezůvky a pití</strong> – pro pohodlí a osvěžení v klubovně</li>
        </ul>
      </div>

      <div class="section-block">
        <strong>Přijďte se za námi podívat!</strong>
        <p class="content-p">Nejste si jistí, jestli je oddíl to pravé? <strong>Žádný problém!</strong> Přijďte se nezávazně podívat na kteroukoli naši schůzku. Rádi vás i vaše děti uvidíme, vše vám ukážeme a vyzkoušíte si, jak to u nás chodí.</p>
        <p class="content-p content-p--highlight">Těšíme se na nové tváře a budoucí kamarády!</p>
      </div>
    </section>

    <section id="tabor" class="tab-content <?php echo ($activeTab === 'tabor') ? 'active' : ''; ?>">
      <h3>Letní tábor TK Rozrazil: Dobrodružství na zelené louce</h3>
      <p><strong>Zažijte s námi léto, na které se nezapomíná.</strong> Náš tábor není jen pobyt v přírodě – je to návrat ke kořenům, učení se samostatnosti a budování přátelství pod širým nebem.</p>

      <div class="section-block">
        <strong>Základní informace</strong>
        <ul class="content-ul">
          <li><strong>Kdy:</strong> 18. července - 1. srpna 2026</li>
          <li><strong>Kde:</strong> Kralice nad Oslavou</li>
          <li><strong>Aktuality:</strong> Přihlášku naleznete <a href="https://tkrozrazil.cz/predbezna_prihlaska_2026.pdf" target="_blank">zde</a>.</li>
        </ul>
      </div>

      <div class="section-block">
        <strong>Jak to u nás vypadá? (Nejčastější dotazy)</strong>
        <p class="content-p">Zakládáme si na <strong>autentickém tábornickém zážitku</strong>. Nečekejte žádné zděné budovy, ale opravdový život v souladu s přírodou:</p>
        <ul class="content-ul">
          <li><strong>Bydlení pod stanem:</strong> Spíme v klasických „dvoulůžkových" stanech s podsadou, které k pravému táboru neodmyslitelně patří</li>
          <li><strong>Tábor na zelené louce:</strong> Celé naše zázemí tvoří pouze stany a zastřešené prostory (jídelna, polní kuchyně a hangár), které nás chrání před nepřízní počasí</li>
          <li><strong>Poctivá kuchyně:</strong> Vaříme si sami! Děti se spolu se svými vedoucími střídají v rámci družinek na službách v kuchyni. Učí se tak zodpovědnosti, pomáhají s přípravou jídla, topením i chodem tábora</li>
          <li><strong>Cesta za dobrodružstvím:</strong> Na tábor vyrážíme stylově vlakem do Kralic nad Oslavou a odtud nás čeká přibližně dvoukilometrová procházka až na tábořiště</li>
          <li><strong>Těžká zavazadla bez obav:</strong> I když jdeme pěšky, kufry a těžká zavazadla dětem odvezeme ze srazu na místo autem</li>
        </ul>
      </div>

      <div class="section-block">
        <strong>Nasajte atmosféru</strong>
        <p class="content-p">Chcete vidět, jak to u nás vypadá v praxi? Podívejte se na fotky z minulých let a prohlédněte si naše tábořiště. Fotogalerii a odkazy na naše sociální sítě najdete v sekci <strong>[Odkazy]</strong>.</p>
      </div>

      <p class="content-highlight-end">Těšíme se na vás v údolí Oslavy!</p>
    </section>

    <section id="fotogalerie" class="tab-content <?php echo ($activeTab === 'fotogalerie') ? 'active' : ''; ?>">
        <h3>Fotogalerie</h3>
      <?php if(isset($_SESSION['user'])): ?>
        <div class="upload-section" id="gallery-upload-section">
          <div class="admin-controls">
            
            <div class="upload-group">
              <div class="upload-header-modern">
                 <h4>Správa fotogalerie</h4>
                 <p>Přetáhněte fotky kamkoliv do tohoto boxu nebo klikněte pro výběr.</p>
              </div>

              <form id="upload-form" action="actions/upload.php" method="post" enctype="multipart/form-data" novalidate>
                
                <div class="upload-modern-container">
                    <div class="upload-drop-overlay">
                        <i class="ri-upload-cloud-2-fill"></i>
                        <span>Pusťte fotky pro nahrání</span>
                    </div>

                    <div class="upload-modern-controls">
                        <div class="file-select-area" onclick="document.getElementById('fotka-input').click()">
                            <i class="ri-image-add-line"></i>
                            <span id="drop-zone-text" class="drop-zone-text--idle">Vybrat fotky z počítače</span>
                            <input id="fotka-input" type="file" name="fotka[]" multiple required accept="image/*" class="hidden-file-input">
                        </div>

                        <div class="upload-modern-filters">
                            <div class="form-row">
                              <label for="rok-select">Rok:</label>
                              <select id="rok-select" name="rok" required>
                                <?php for ($r = 2020; $r <= 2026; $r++) echo "<option value='$r' " . (date('Y') == $r ? 'selected' : '') . ">$r</option>"; ?>
                              </select>
                            </div>
                            <div class="form-row">
                              <label for="kat-select">Kategorie:</label>
                              <select id="kat-select" name="kategorie" required>
                                <option value="" disabled selected>-- Vyberte kategorii --</option>
                                <option value="tabor">Tábor</option>
                                <option value="expedice">Expedice</option>
                              </select>
                            </div>
                            <button type="submit" class="btn primary btn-upload-modern">
                                <i class="ri-upload-2-line"></i> Nahrát fotky
                            </button>
                        </div>
                    </div>
                </div>
              </form>
            </div>

            <div class="delete-group" id="deleteSelectedBtnGroup" style="display: none;">
                <button type="button" class="btn danger js-unified-delete btn-delete-modern" 
                        data-title="Smazat fotky" 
                        data-msg="Opravdu chcete smazat vybrané fotky? Tato akce je nevratná." 
                        data-target-form="delete-form">
                        <i class="ri-delete-bin-line"></i> Smazat vybrané
                </button>
              </div>

          </div>
        </div>
      <?php endif; ?>

      <?php $active_rok = isset($_GET['rok']) ? intval($_GET['rok']) : null; ?>
        <div class="year-filter">
      <?php for ($r = 2020; $r <= 2026; $r++): ?>
        <a href="?rok=<?php echo $r; ?>" 
          class="year-link <?php echo ($r === $active_rok) ? 'active' : ''; ?>" 
          title="Fotky z roku <?php echo $r; ?>"><?php echo $r; ?></a>
      <?php endfor; ?>
      </div>

      <?php if (isset($_GET['rok'])): 
        $vybrany_rok = intval($_GET['rok']);
        $vybrana_kat = isset($_GET['kat']) ? $_GET['kat'] : ''; 
      ?>
        <div class="category-filter">
          <h4>Vyberte akci pro rok <?php echo $vybrany_rok; ?>:</h4>
          <div class="category-buttons">
            <a href="?rok=<?php echo $vybrany_rok; ?>&kat=tabor" 
            class="cat-link <?php echo ($vybrana_kat === 'tabor') ? 'active' : ''; ?>">Tábor</a>
            <a href="?rok=<?php echo $vybrany_rok; ?>&kat=expedice" 
            class="cat-link <?php echo ($vybrana_kat === 'expedice') ? 'active' : ''; ?>">Expedice</a>
          </div>
        </div>
      <?php endif; ?>

      <div class="photo-grid">
        <?php
        if (isset($_GET['rok']) && isset($_GET['kat'])) {
            $vybrany_rok = intval($_GET['rok']);
            $vybrana_kat = $_GET['kat'];
            
            if ($conn) {
                $stmt = $conn->prepare("SELECT id, nazev_souboru FROM fotky WHERE rok = ? AND popis = ? ORDER BY datum_nahrani DESC");
                $stmt->bind_param("is", $vybrany_rok, $vybrana_kat);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result && $result->num_rows > 0) {
                    echo '<form id="delete-form" action="actions/delete_photos.php" method="post">';
                    echo '<input type="hidden" name="redirect_rok" value="'.$vybrany_rok.'">';
                    echo '<input type="hidden" name="redirect_kat" value="'.htmlspecialchars($vybrana_kat).'">';
                    echo "<div class='photo-grid-container'>";
                    $i = 0;
                    while($row = $result->fetch_assoc()) {
                        $id = $row['id']; 
                        
                        $db_path = trim($row['nazev_souboru']);
                        $clean_path = str_replace('../', '', $db_path);
                        $parts = explode('/', $clean_path);
                        $encoded_parts = array_map('rawurlencode', $parts);
                        $src = implode('/', $encoded_parts);

                        echo "<div class='photo-item'>";
                        if (isset($_SESSION['user'])) { 
                            echo "<input type='checkbox' name='selected_photos[]' value='$id' class='delete-checkbox' aria-label='Vybrat fotku'>"; 
                        }
                        echo "<img src='$src' class='gallery-img' onclick='openLightbox($i)' alt='Fotka $i' loading='lazy'>";
                        echo "</div>";
                        $i++;
                    }
                    echo "</div></form>";
                } else { 
                    echo "<p class='empty-msg'>V kategorii <strong>" . ucfirst(htmlspecialchars($vybrana_kat)) . "</strong> pro rok <strong>$vybrany_rok</strong> zatím nejsou žádné fotky.</p>"; 
                }
                $stmt->close();
            }
        } ?>
      </div>
    </section>

    <section id="kontakt" class="tab-content <?php echo ($activeTab === 'kontakt') ? 'active' : ''; ?>">
      <h3>Kontakt</h3>
      <div class="contact-table-wrapper">
        <table class="contact-table">
            <tbody>
                <tr>
                    <td><strong>Název organizace</strong></td>
                    <td>Česká tábornická unie - T.K. Rozrazil Brno, p.s.</td>   
                </tr>
                <tr>
                    <td><strong>IČ</strong></td>
                    <td>67027148</td>
                </tr>
                <tr>
                    <td><strong>Sídlo</strong></td>
                    <td>Hradecká 1879/12, 612 00, Brno</td>
                </tr>
                <tr>
                    <td><strong>E-mail</strong></td>
                    <td>rozraziltk@gmail.com</td>
                </tr>
                <tr>
                    <td><strong>Náčelník</strong></td>
                    <td>MUDr. Jan Pospíšil</td>
                </tr>
                <tr>
                    <td><strong>Telefon</strong></td>
                    <td>+420 607 290 414</td>
                </tr>
            </tbody>
        </table>
      </div>
      
      <div class="section-divider">
        <div class="section-header-row">
            <h3 class="section-subtitle">Dokumenty ke stažení</h3>
            <?php if(isset($_SESSION['user'])): ?>
              <button class="btn primary btn-add-new btn-static" data-modal-target="documentModal">+ Přidat dokument</button>
            <?php endif; ?>
          </div>

          <div class="documents-list">
            <?php
            if ($conn) {
                $res = $conn->query("SELECT * FROM dokumenty ORDER BY uploaded_at DESC");
                if ($res && $res->num_rows > 0) {
                    while($doc = $res->fetch_assoc()) {
                        
                        $full_filename = $doc['filename'];
                        $display_filename = $full_filename;
                        $parts = explode('_', $full_filename, 2);
                        if(count($parts) > 1) {
                            $display_filename = $parts[1];
                        }
                        $extension = strtolower(pathinfo($full_filename, PATHINFO_EXTENSION));
                        
                        $icon_class  = "ri-file-text-line"; 
                        $icon_color_class = "document-icon--default";

                        if ($extension === 'pdf') {
                            $icon_class  = "ri-file-pdf-2-line";
                            $icon_color_class = "document-icon--pdf";
                        } elseif (in_array($extension, ['xls', 'xlsx', 'csv'])) {
                            $icon_class  = "ri-file-excel-2-line";
                            $icon_color_class = "document-icon--excel";
                        }

                        echo '<div class="document-item">';
                            echo '<div class="document-info">';
                                echo '<div class="document-icon-wrapper">';
                                    echo '<i class="' . $icon_class . ' document-icon ' . $icon_color_class . '"></i>';
                                echo '</div>';
                                echo '<div class="document-text">';
                                    echo '<span class="document-title-text" title="' . htmlspecialchars($doc['title']) . '">' . htmlspecialchars($doc['title']) . '</span>';
                                    echo '<span class="document-subtitle" title="' . htmlspecialchars($display_filename) . '">' . htmlspecialchars($display_filename) . '</span>';
                                echo '</div>';
                            echo '</div>';
                            echo '<div class="document-actions">';
                                echo '<a href="uploads/Dokumenty/' . $doc['filename'] . '" download class="btn ghost document-action-btn">';
                                    echo '<i class="ri-download-line"></i> Stáhnout'; 
                                echo '</a>';
                                if(isset($_SESSION['user'])) {
                                    echo '<button type="button" class="btn danger document-action-btn js-unified-delete" 
                                            data-title="Smazat dokument" 
                                            data-msg="Opravdu chcete smazat tento dokument?" 
                                            data-url="actions/delete_document.php?id=' . $doc['id'] . '" title="Smazat">';
                                        echo '<i class="ri-delete-bin-line"></i>';
                                    echo '</button>';
                                }
                            echo '</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<p class="empty-state-text">Žádné dokumenty nejsou k dispozici.</p>';
                }
            }
            ?>
          </div>
      </div>

      <div class="section-divider">
        <div class="section-header-row">
            <h3 class="section-subtitle">Vedení klubu</h3>
            <?php if(isset($_SESSION['user'])): ?>
              <button class="btn primary btn-add-new btn-static" data-modal-target="memberModal">+ Přidat člena</button>
            <?php endif; ?>
        </div>
        <div class="members-grid">
          <?php
          if ($conn) {
              $sql = "SELECT id, jmeno, pozice, email, telefon, fotka AS fotografie, zajmy AS popis, fotka, zajmy FROM clenove ORDER BY jmeno ASC";
              $result = $conn->query($sql);
              
              if ($result && $result->num_rows > 0) {
                  while($member = $result->fetch_assoc()) {
                      $member_json = htmlspecialchars(json_encode($member), ENT_QUOTES, 'UTF-8');
                      $initials = mb_substr($member['jmeno'], 0, 1);
                      
                      echo '<div class="member-card js-open-member-detail" data-id="' . $member['id'] . '" data-member=\'' . $member_json . '\'>';
                      echo '<div class="member-photo">';
                      if (!empty($member['fotka']) && $member['fotka'] !== 'default.jpg') {
                          echo '<img src="uploads/Clenove/' . rawurlencode($member['fotka']) . '" alt="' . htmlspecialchars($member['jmeno']) . '">';
                      } else {
                          echo '<div class="member-avatar">' . htmlspecialchars($initials) . '</div>';
                      }
                      echo '</div>';
                      
                      echo '<div class="member-info">';
                      echo '<h5>' . htmlspecialchars($member['jmeno']) . '</h5>';
                      echo '<p>' . htmlspecialchars($member['pozice']) . '</p>';
                      echo '</div>';
                      
                      if (isset($_SESSION['user'])) {
                          echo '<div class="member-actions">';
                          echo '<button type="button" class="btn-icon js-edit-member" data-member=\'' . $member_json . '\' title="Upravit">';
                          echo '<i class="ri-pencil-line"></i></button>';
                          echo '<button type="button" class="btn-icon danger js-unified-delete" 
                                  data-title="Smazat člena" 
                                  data-msg="Opravdu chcete smazat tohoto člena?" 
                                  data-url="actions/delete_member.php?id=' . $member['id'] . '" title="Smazat">';
                          echo '<i class="ri-delete-bin-line"></i></button>';
                          echo '</div>';
                      }
                      echo '</div>';
                  }
              } else {
                  echo '<p class="members-empty">Zatím nejsou žádní členové.</p>';
              }
          }
          ?>
        </div>
      </div>

      <div class="section-divider">
        <h3 class="section-subtitle">Sociální sítě a odkazy</h3>
        <p class="section-subtitle-desc">Sledujte nás a zůstaňte v obraze — fotky, novinky a videa ze života oddílu.</p>

        <div class="social-cards-row">
          <a href="https://www.instagram.com/tkrozrazil/" target="_blank" class="social-card">
            <div class="social-card-icon social-icon-instagram">
              <i class="ri-instagram-line"></i>
            </div>
            <div class="social-card-text">
              <span class="social-card-name">Instagram</span>
              <span class="social-card-handle">@tkrozrazil</span>
            </div>
            <i class="ri-external-link-line social-card-arrow"></i>
          </a>
          <a href="https://www.facebook.com/TKRozrazil" target="_blank" class="social-card">
            <div class="social-card-icon social-icon-facebook">
              <i class="ri-facebook-circle-fill"></i>
            </div>
            <div class="social-card-text">
              <span class="social-card-name">Facebook</span>
              <span class="social-card-handle">TK Rozrazil</span>
            </div>
            <i class="ri-external-link-line social-card-arrow"></i>
          </a>
          <a href="https://www.youtube.com/channel/UC3dXn5N2EH6oVXwA3H-0E1A" target="_blank" class="social-card">
            <div class="social-card-icon social-icon-youtube">
              <i class="ri-youtube-line"></i>
            </div>
            <div class="social-card-text">
              <span class="social-card-name">YouTube</span>
              <span class="social-card-handle">TK Rozrazil</span>
            </div>
            <i class="ri-external-link-line social-card-arrow"></i>
          </a>
          <a href="https://rozraziltk.rajce.idnes.cz//" target="_blank" class="social-card">
            <div class="social-card-icon social-icon-rajce">
              <img src="img/rajce1.png" class="social-icon-img" alt="Rajče">
            </div>
            <div class="social-card-text">
              <span class="social-card-name">Rajče</span>
              <span class="social-card-handle">Fotogalerie</span>
            </div>
            <i class="ri-external-link-line social-card-arrow"></i>
          </a>
        </div>
        
        <div class="section-divider">
          <h3 class="section-subtitle">Česká tábornická unie</h3>
          <p class="section-subtitle-desc">Jsme hrdým členem Jihomoravské oblasti ČTU.</p>
          <div class="social-cards-row">
              <a href="https://tabornici.cz" target="_blank" class="social-card">
                  <div class="social-card-icon ctu-icon-bg">
                      <img src="img/ctu-logo.png" alt="ČTU Logo" class="ctu-logo-img">
                  </div>
                  <div class="social-card-text">
                      <span class="social-card-name">ČTU - Česká tábornická unie</span>
                      <span class="social-card-handle">tabornici.cz</span>
                  </div>
                  <i class="ri-external-link-line social-card-arrow"></i>
              </a>
              <a href="https://ctujm.cz" target="_blank" class="social-card">
                  <div class="social-card-icon ctu-icon-bg">
                      <img src="img/ctu-jih-logo.png" alt="ČTU JM Logo" class="ctu-logo-img">
                  </div>
                  <div class="social-card-text">
                      <span class="social-card-name">ČTU - Jihomoravská oblast</span>
                      <span class="social-card-handle">ctujm.cz</span>
                  </div>
                  <i class="ri-external-link-line social-card-arrow"></i>
              </a>
          </div>
        </div>
      </div>
    </section>
  </main>

  <footer class="footer-main">
    <div class="footer-buttons">
      <a href="https://www.instagram.com/tkrozrazil/" class="footer-btn" target="_blank" title="Instagram">
        <i class="ri-instagram-line"></i>
      </a>
      
      <a href="https://www.facebook.com/TKRozrazil" class="footer-btn" target="_blank" title="Facebook">
        <i class="ri-facebook-circle-fill"></i>
      </a>

      <button onclick="scrollToTop()" class="footer-btn-center" title="Nahoru">
        <i class="ri-arrow-up-double-line"></i>
      </button>

      <a href="https://www.youtube.com/channel/UC3dXn5N2EH6oVXwA3H-0E1A" class="footer-btn" target="_blank" title="YouTube">
        <i class="ri-youtube-line"></i>
      </a>

      <a href="https://rozraziltk.rajce.idnes.cz/" class="footer-btn" target="_blank" title="Rajče Fotogalerie">
    <img src="img/rajce1.png" class="footer-icon-img" alt="Rajče">
    </a>
    </div>

    <div class="footer-info">
    <div>© 2026 TK Rozrazil. Všechna práva vyhrazena.</div>
    <div class="footer-creators">
        Design &amp; Code: 
        
        <a href="https://www.linkedin.com/in/kry%C5%A1tof-herec-6397743aa?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" 
           target="_blank" 
           class="footer-creator-link">
            Kryštof Herec <i class="ri-linkedin-box-fill footer-li-icon"></i>
        </a>

        <span class="footer-separator">&amp;</span>

        <a href="https://www.linkedin.com/in/david-ku%C4%8Dera-1a35753aa?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" 
           target="_blank" 
           class="footer-creator-link">
            David Kučera <i class="ri-linkedin-box-fill footer-li-icon"></i>
        </a>
    </div>
</div>
  </footer>

  <div id="newsModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-wide">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-newspaper-line"></i></div>
        <div>
          <h2 class="settings-title">Přidat aktualitu</h2>
          <p class="settings-subtitle">Publikovat novou zprávu na web</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('newsModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/add_news.php" method="post" enctype="multipart/form-data">
          <div class="form-row">
            <label>Nadpis</label>
            <input name="title" type="text" required placeholder="Nadpis aktuality">
          </div>
          <div class="form-row form-row-flex">
              <div class="flex-1">
                  <label>Datum Od (Začátek)</label>
                  <input name="datum" type="date" required>
              </div>
              <div class="flex-1">
                  <label>Datum Do (Nepovinné)</label>
                  <input name="datum_do" type="date">
              </div>
          </div>
          <div class="form-row">
              <label>Odkaz na proklik</label>
              <input name="odkaz" type="url" placeholder="https://...">
          </div>
          <div class="form-row">
              <label>Obrázky</label>
              <input type="file" name="image[]" accept="image/*" multiple>
          </div>
          <div class="form-row">
            <label>Obsah</label>
            <textarea name="content" rows="5" required placeholder="Text aktuality..."></textarea>
            <span class="link-syntax-hint">Odkaz vložíte syntaxí: <code>[Klikni zde](https://adresa.cz)</code></span>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('newsModal')">Zrušit</button>
            <button type="submit" class="btn primary">Publikovat</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="editNewsModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-wide">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-edit-2-line"></i></div>
        <div>
          <h2 class="settings-title">Upravit aktualitu</h2>
          <p class="settings-subtitle">Editace existující zprávy</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('editNewsModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/edit_news.php" method="post" enctype="multipart/form-data" id="editNewsForm">
          <input type="hidden" name="id" id="edit_news_id">
          <div class="form-row">
            <label>Nadpis</label>
            <input name="title" id="edit_news_title" type="text" required>
          </div>
          <div class="form-row form-row-flex">
              <div class="flex-1">
                <label>Datum Od</label>
                <input name="datum" id="edit_news_date" type="date" required>
              </div>
              <div class="flex-1">
                <label>Datum Do</label>
                <input name="datum_do" id="edit_news_date_do" type="date">
              </div>
          </div>
          <div class="form-row">
            <label>Odkaz na proklik</label>
            <input name="odkaz" id="edit_news_link" type="url">
          </div>
          <div class="form-row">
            <label>Obsah</label>
            <textarea name="content" id="edit_news_content" rows="5" required></textarea>
            <span class="link-syntax-hint">Odkaz vložíte syntaxí: <code>[Klikni zde](https://adresa.cz)</code></span>
          </div>
          <div class="form-row form-row-section">
            <label class="label-section">Fotografie</label>
            <div id="existingNewsImagesContainer" class="existing-images-container"></div>
            <button type="button" class="btn danger" id="deleteSelectedNewsImagesBtn" onclick="submitDeleteNewsImages(event)" style="display:none;">🗑 Smazat vybrané fotky</button>
            <label class="label-mt">Přidat nové fotky</label>
            <input type="file" name="image[]" accept="image/*" multiple class="file-input-mt">
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('editNewsModal')">Zrušit</button>
            <button type="submit" class="btn primary">Uložit změny</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="postModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-wide">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-calendar-event-line"></i></div>
        <div>
          <h2 class="settings-title">Přidat akci</h2>
          <p class="settings-subtitle">Naplánovat novou událost v kalendáři</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('postModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/add_post.php" method="post" enctype="multipart/form-data">
          <div class="form-row">
            <label>Nadpis</label>
            <input name="title" type="text" placeholder="Zadejte nadpis článku" required>
          </div>
          <div class="form-row form-row-flex">
              <div class="flex-1">
                  <label>Datum Od (Začátek)</label>
                  <input name="datum" type="date" required>
              </div>
              <div class="flex-1">
                  <label>Datum Do (Nepovinné)</label>
                  <input name="datum_do" type="date">
              </div>
          </div>
          <div class="form-row">
            <label>Odkaz na proklik</label>
            <input name="odkaz" type="url" placeholder="https://...">
          </div>
          <div class="form-row">
            <label>Obrázky</label>
            <input type="file" name="image[]" accept="image/*" multiple>
          </div>
          <div class="form-row">
            <label>Obsah</label>
            <textarea name="content" rows="5" placeholder="Zde napište text článku..." required></textarea>
            <span class="link-syntax-hint">Odkaz vložíte syntaxí: <code>[Klikni zde](https://adresa.cz)</code></span>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('postModal')">Zrušit</button>
            <button type="submit" class="btn primary">Publikovat</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="editPostModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-wide">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-edit-2-line"></i></div>
        <div>
          <h2 class="settings-title">Upravit akci</h2>
          <p class="settings-subtitle">Editace existující události v kalendáři</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('editPostModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/edit_post.php" method="post" enctype="multipart/form-data" id="editPostForm">
          <input type="hidden" name="id" id="edit_post_id">
          <div class="form-row">
            <label>Nadpis</label>
            <input name="title" id="edit_post_title" type="text" required>
          </div>
          <div class="form-row form-row-flex">
              <div class="flex-1">
                <label>Datum Od</label>
                <input name="datum" id="edit_post_date" type="date" required>
              </div>
              <div class="flex-1">
                <label>Datum Do</label>
                <input name="datum_do" id="edit_post_date_do" type="date">
              </div>
          </div>
          <div class="form-row">
            <label>Odkaz na proklik</label>
            <input name="odkaz" id="edit_post_link" type="url">
          </div>
          <div class="form-row">
            <label>Obsah</label>
            <textarea name="content" id="edit_post_content" rows="5" required></textarea>
            <span class="link-syntax-hint">Odkaz vložíte syntaxí: <code>[Klikni zde](https://adresa.cz)</code></span>
          </div>
          <div class="form-row form-row-section">
            <label class="label-section">Fotografie</label>
            <div id="existingImagesContainer" class="existing-images-container"></div>
            <button type="button" class="btn danger" id="deleteSelectedImagesBtn" onclick="submitDeleteImages(event)">🗑 Smazat vybrané fotky</button>
            <label class="label-mt">Přidat nové fotky (až 3)</label>
            <input type="file" name="image[]" accept="image/*" multiple class="file-input-mb">
            <small class="form-hint">Můžete nahrát až 3 fotky najednou.</small>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('editPostModal')">Zrušit</button>
            <button type="submit" class="btn primary">Uložit změny</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="loginModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal">
      <button type="button" class="modal-x-btn" onclick="closeModal('loginModal')" aria-label="Zavřít">✕</button>
      <h2>Přihlášení</h2>
      <form action="login.php" method="post">
        <div class="form-row">
          <label for="username">Uživatelské jméno</label>
          <input id="username" name="username" type="text" required autocomplete="username">
        </div>
        <div class="form-row">
          <label for="passwordInput">Heslo</label>
          <div class="password-wrapper">
            <input id="passwordInput" name="password" type="password" required autocomplete="current-password">
            <button type="button" id="togglePassword" aria-label="Ukázat heslo">Ukázat</button>
          </div>
        </div>
        <div class="form-row recaptcha-row">
            <div class="g-recaptcha" data-sitekey="6Lc0WVksAAAAAJTCt1O9unpUWXLs2xyXg5FXewJ4"></div>
        </div>
        <div class="form-actions">
          <button type="button" class="btn ghost" onclick="closeModal('loginModal')">Zavřít</button>
          <button type="submit" class="btn primary">Přihlásit</button>
        </div>
      </form>
    </div>
  </div>

  <div id="detailModal" class="modal-backdrop" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="modal settings-modal" style="max-width: 900px !important;">
      
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-article-line"></i></div>
        <div style="flex: 1; min-width: 0;">
          <h2 class="settings-title" id="detailTitle" style="white-space: normal; line-height: 1.3; font-size: 22px;"></h2>
          <p class="settings-subtitle" id="detailDate" style="margin-top: 4px; opacity: 0.9; font-size: 13px;"></p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('detailModal')" aria-label="Zavřít">✕</button>
      </div>

      <div class="settings-body">
        <div class="content-text" id="detailContent"></div>
        
        <div id="detailImageContainer" class="detail-images-dynamic-grid">
           </div>
        
        <div id="detailLinkContainer" class="detail-link-container">
          <a id="detailLink" href="#" target="_blank" class="btn primary">Více informací</a>
        </div>

        <div id="detailAuthor" style="margin-top: 10px; margin-bottom: 15px; font-size: 12px; color: #999; text-align: right; display: none;"></div>
        
        <div class="form-actions" style="margin-top: 30px;">
          <button type="button" class="btn ghost" onclick="closeModal('detailModal')">Zavřít</button>
        </div>
      </div>
      
    </div>
  </div>

  <div id="detailLightbox" role="dialog" aria-modal="true" aria-hidden="true">
    <span class="close-detail-lightbox" onclick="closeDetailLightbox()" aria-label="Zavřít">×</span>
    <span class="detail-nav-arrow detail-prev" onclick="changeDetailImage(-1)" aria-label="Předchozí">&#8249;</span>
    <img id="detailLightbox-img" src="" alt="Fotka">
    <span class="detail-nav-arrow detail-next" onclick="changeDetailImage(1)" aria-label="Další">&#8250;</span>
    <div id="detailLightbox-counter"></div>
  </div>

  <div id="lightbox" class="lightbox" role="dialog" aria-modal="true" aria-hidden="true">
    <span class="close-lightbox" onclick="closeLightbox()" aria-label="Zavřít">×</span>
    <span class="nav-arrow prev" onclick="changeImage(-1)" aria-label="Předchozí">&#8249;</span>
    <img id="lightbox-img" src="" alt="Fotka">
    <span class="nav-arrow next" onclick="changeImage(1)" aria-label="Další">&#8250;</span>
    <div id="lightbox-counter" class="lightbox-counter"></div>
  </div>

  <div id="documentModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-file-upload-line"></i></div>
        <div>
          <h2 class="settings-title">Přidat dokument</h2>
          <p class="settings-subtitle">Nahrát nový soubor ke stažení</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('documentModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/add_document.php" method="post" enctype="multipart/form-data">
          <div class="form-row">
            <label>Název dokumentu</label>
            <input name="title" type="text" placeholder="Zadejte název dokumentu" required>
          </div>
          <div class="form-row">
            <label>Soubor (PDF, Word, Excel…)</label>
            <input name="dokument" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt" required>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('documentModal')">Zrušit</button>
            <button type="submit" class="btn primary">Nahrát</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="memberModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-user-add-line"></i></div>
        <div>
          <h2 class="settings-title">Přidat člena</h2>
          <p class="settings-subtitle">Přidat osobu do vedení klubu</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('memberModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/add_member.php" method="post" enctype="multipart/form-data">
          <div class="form-row">
            <label>Jméno a příjmení *</label>
            <input name="jmeno" type="text" placeholder="Zadejte celé jméno" required>
          </div>
          <div class="form-row">
            <label>Pozice / Funkce</label>
            <input name="pozice" type="text" placeholder="Např. Náčelník, Rádce…">
          </div>
          <div class="form-row">
            <label>E-mail</label>
            <input name="email" type="email" placeholder="email@purkynka.cz">
          </div>
          <div class="form-row">
            <label>Telefon</label>
            <input name="telefon" type="text" placeholder="+420 123 456 789">
          </div>
          <div class="form-row">
            <label>Popis / Zajímavosti</label>
            <textarea name="zajmy" rows="3" placeholder="Krátký popis…"></textarea>
          </div>
          <div class="form-row">
            <label>Fotografie</label>
            <input name="fotka" type="file" accept="image/*">
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('memberModal')">Zrušit</button>
            <button type="submit" class="btn primary">Přidat člena</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="editMemberModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-medium">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-user-settings-line"></i></div>
        <div>
          <h2 class="settings-title">Upravit člena</h2>
          <p class="settings-subtitle">Úprava údajů o členovi vedení</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('editMemberModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/edit_member.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="id" id="edit_member_id">
          <div class="form-row">
            <label>Jméno a příjmení *</label>
            <input name="jmeno" id="edit_member_name" type="text" required>
          </div>
          <div class="form-row">
            <label>Pozice / Funkce</label>
            <input name="pozice" id="edit_member_position" type="text">
          </div>
          <div class="form-row">
            <label>E-mail</label>
            <input name="email" id="edit_member_email" type="email">
          </div>
          <div class="form-row">
            <label>Telefon</label>
            <input name="telefon" id="edit_member_phone" type="text">
          </div>
          <div class="form-row">
            <label>Popis / Zajímavosti</label>
            <textarea name="zajmy" id="edit_member_description" rows="3"></textarea>
          </div>
          <div class="form-row form-row-section">
            <label class="label-section">Nová fotografie (nepovinné)</label>
            <input name="fotka" type="file" accept="image/*" class="file-input-mt">
            <small class="form-hint">Pokud nevyberete nový soubor, zůstane původní fotografie.</small>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('editMemberModal')">Zrušit</button>
            <button type="submit" class="btn primary">Uložit změny</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="memberDetailModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal modal-sm">
      <button type="button" class="modal-x-btn" onclick="closeModal('memberDetailModal')" aria-label="Zavřít">✕</button>
      <div class="member-detail-photo" id="memberDetailPhoto"></div>
      <h2 id="memberDetailName" class="member-detail-name"></h2>
      <p id="memberDetailPosition" class="member-detail-position"></p>
      <div id="memberDetailInfo" class="member-detail-info"></div>
      <div class="form-actions">
        <button type="button" class="btn ghost" onclick="closeModal('memberDetailModal')">Zavřít</button>
      </div>
    </div>
  </div>

  <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
  <div id="settingsModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal">
      <div class="settings-header">
        <div class="settings-header-icon">⚙</div>
        <div>
          <h2 class="settings-title">Správa uživatelů</h2>
          <p class="settings-subtitle">Správa uživatelů a rolí</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('settingsModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <div class="settings-section-label">
          <i class="ri-group-line"></i> Uživatelé
        </div>

        <?php
          if ($conn) {
              $users_result = $conn->query("SELECT login, role FROM uzivatele ORDER BY login ASC");
              if ($users_result && $users_result->num_rows > 0):
        ?>
        <div class="settings-user-list">
          <?php while($u = $users_result->fetch_assoc()):
            $isMe = ($u['login'] === $_SESSION['user']);
            $initials = strtoupper(mb_substr($u['login'], 0, 1));
            $roleClass = $u['role'] === 'admin' ? 'role-admin' : 'role-editor';
            $roleLabel = $u['role'] === 'admin' ? 'Admin' : 'Editor';
          ?>
          <div class="settings-user-row">
            <div class="settings-user-info">
              <span class="settings-user-name"><?php echo htmlspecialchars($u['login']); ?></span>
              <?php if($isMe): ?><span class="settings-user-you">vy</span><?php endif; ?>
            </div>
            <span class="settings-role-badge <?php echo $roleClass; ?>"><?php echo $roleLabel; ?></span>
            <?php if(!$isMe): ?>
            <button class="settings-delete-btn js-unified-delete" 
                    data-title="Smazat uživatele" 
                    data-msg="Opravdu chcete smazat uživatele <?php echo htmlspecialchars($u['login']); ?>?" 
                    data-url="actions/delete_user.php?login=<?php echo htmlspecialchars($u['login'], ENT_QUOTES); ?>" 
                    data-return-modal="settingsModal" 
                    title="Smazat uživatele">
              <i class="ri-delete-bin-line"></i>
            </button>
            <?php else: ?>
            <div class="spacer-36"></div>
            <?php endif; ?>
          </div>
          <?php endwhile; ?>
        </div>
        <?php
              endif;
          }
        ?>

        <div class="settings-divider">
          <span>Přidat nového uživatele</span>
        </div>

        <form action="actions/add_user.php" method="post">
          <div class="form-row">
            <label>Přihlašovací jméno</label>
            <input name="new_login" type="text" required placeholder="Uživatelské jméno">
          </div>
          <div class="form-row">
            <label>Heslo</label>
            <div class="password-wrapper">
              <input id="newPasswordInput" name="new_password" type="password" required placeholder="Heslo">
              <button type="button" id="toggleNewPassword" aria-label="Ukázat heslo">Ukázat</button>
            </div>
          </div>
          <div class="form-row">
            <label>Role</label>
            <select name="new_role">
              <option value="editor">Editor</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('settingsModal')">Zavřít</button>
            <button type="submit" class="btn primary">+ Přidat uživatele</button>
          </div>
        </form>
      </div></div>
  </div>
  <?php endif; ?>

  <div id="banerModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-medium">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-image-add-line"></i></div>
        <div>
          <h2 class="settings-title">Přidat banner</h2>
          <p class="settings-subtitle">Nový plakát nebo oznámení</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('banerModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/add_baner.php" method="post" enctype="multipart/form-data">
          <div class="form-row">
            <label>Obrázek (čtvercový, max 5 MB) *</label>
            <input type="file" name="image" accept="image/*" required>
          </div>
          <div class="form-row">
            <label>Text příspěvku *</label>
            <textarea name="content" rows="4" required placeholder="Popis banneru nebo plakátu…"></textarea>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('banerModal')">Zrušit</button>
            <button type="submit" class="btn primary">Publikovat</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="editBanerModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal settings-modal modal-medium">
      <div class="settings-header">
        <div class="settings-header-icon"><i class="ri-edit-2-line"></i></div>
        <div>
          <h2 class="settings-title">Upravit banner</h2>
          <p class="settings-subtitle">Změnit obsah banneru</p>
        </div>
        <button type="button" class="modal-x-btn" onclick="closeModal('editBanerModal')" aria-label="Zavřít">✕</button>
      </div>
      <div class="settings-body">
        <form action="actions/edit_baner.php" method="post" enctype="multipart/form-data">
          <input type="hidden" name="id" id="edit_baner_id">
          <div class="form-row">
            <label>Nový obrázek (nepovinné)</label>
            <input type="file" name="image" accept="image/*">
            <small class="form-hint">Pokud nevyberete nový soubor, zůstane původní.</small>
          </div>
          <div class="form-row">
            <label>Text příspěvku *</label>
            <textarea name="content" id="edit_baner_content" rows="4" required placeholder="Text banneru…"></textarea>
          </div>
          <div class="form-actions">
            <button type="button" class="btn ghost" onclick="closeModal('editBanerModal')">Zrušit</button>
            <button type="submit" class="btn primary">Uložit změny</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="unifiedDeleteModal" class="modal-backdrop" role="dialog" aria-hidden="true">
    <div class="modal">
      <button type="button" class="modal-x-btn" onclick="closeModal('unifiedDeleteModal')" aria-label="Zavřít">✕</button>
      <h2 id="unifiedDeleteTitle">Smazat položku</h2>
      <p id="unifiedDeleteText">Opravdu chcete tuto položku smazat?</p>
      <div class="form-actions">
        <button type="button" id="unifiedDeleteCancelBtn" class="btn ghost">Zrušit</button>
        <a id="unifiedDeleteLink" href="#" class="btn danger" style="display:none;">Ano, smazat</a>
        <button id="unifiedDeleteFormBtn" type="button" class="btn danger" style="display:none;">Ano, smazat</button>
      </div>
    </div>
  </div>

  <script>
      const ROUTE_TAB = "<?php echo htmlspecialchars($base_url); ?>";
      const ROUTE_ITEM_ID = <?php echo $open_item_id ? $open_item_id : 'null'; ?>;
  </script>
  <script src="javascript/main.js?v=<?php echo $js_ver; ?>"></script>
</body>
</html>

<div id="calendarModal" class="modal-backdrop" role="dialog" aria-hidden="true">
  <div class="modal settings-modal modal-wide">
    <div class="settings-header">
      <div class="settings-header-icon"><i class="ri-calendar-event-fill"></i></div>
      <div>
        <h2 class="settings-title">Kalendář akcí</h2>
        <p class="settings-subtitle">Přehled všech plánovaných událostí oddílu</p>
      </div>
      <button type="button" class="modal-x-btn" onclick="closeModal('calendarModal')" aria-label="Zavřít">✕</button>
    </div>
    
    <div class="settings-body">
      <div class="calendar-wrapper">
        <div class="calendar-header">
            <button type="button" id="prevMonth" class="btn-icon"><i class="ri-arrow-left-s-line"></i></button>
            <h3 id="calendarMonthYear" style="margin: 0; color: var(--primary-blue); font-size: 20px;"></h3>
            <button type="button" id="nextMonth" class="btn-icon"><i class="ri-arrow-right-s-line"></i></button>
        </div>
        
        <div class="calendar-legend">
            <span class="legend-item"><span class="legend-color legend-past"></span> Proběhlé akce</span>
            <span class="legend-item"><span class="legend-color legend-future"></span> Plánované akce</span>
        </div>
        
        <div class="calendar-grid">
            <div class="calendar-weekdays">
                <div>Po</div><div>Út</div><div>St</div><div>Čt</div><div>Pá</div><div>So</div><div>Ne</div>
            </div>
            <div id="calendarDays" class="calendar-days"></div>
        </div>
      </div>

      <div class="form-actions" style="margin-top: 20px;">
        <button type="button" class="btn ghost" onclick="closeModal('calendarModal')">Zavřít</button>
      </div>
    </div>
  </div>
</div>

<?php
//vytazeni akci a aktualit do kalendare
$calendar_events = [];
if ($conn) {
    // Akce
    $res_akce = $conn->query("SELECT id, title, datum, datum_do, 'akce' as typ FROM posts");
    if ($res_akce) {
        while($r = $res_akce->fetch_assoc()) { $calendar_events[] = $r; }
    }
    // Aktuality
    $res_novinky = $conn->query("SELECT id, title, datum, datum_do, 'novinka' as typ FROM aktuality");
    if ($res_novinky) {
        while($r = $res_novinky->fetch_assoc()) { $calendar_events[] = $r; }
    }
}
?>
<script>
    const CALENDAR_EVENTS = <?php echo json_encode($calendar_events); ?>;
</script>

<div id="calEventModal" class="modal-backdrop" role="dialog" aria-hidden="true">
  <div class="modal modal-sm">
    <button type="button" class="modal-x-btn" onclick="closeModal('calEventModal')" aria-label="Zavřít">✕</button>
    <h2 id="calEventTitle" style="color: var(--secondary-blue); margin-top: 0; font-size: 24px;"></h2>
    <div class="meta-date" id="calEventDate" style="margin-bottom: 25px; display: inline-block; background: #edf2f7; padding: 6px 12px; border-radius: 4px; font-weight: bold; color: var(--text-gray);"></div>
    <div class="form-actions">
      <button type="button" class="btn ghost" onclick="closeModal('calEventModal')">Zavřít</button>
      <a id="calEventLink" href="#" class="btn primary" style="display: flex; align-items: center; justify-content: center; text-decoration: none;">Více informací</a>
    </div>
  </div>
</div>