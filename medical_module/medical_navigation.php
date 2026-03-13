<?php

$_nav_current = basename($_SERVER['PHP_SELF']);

function _nav_link(string $href, string $icon, string $label, string $current): string {
    $base = basename($href);
    $active = ($base === $current) ? ' active' : '';
    return sprintf(
        '<a class="nav-link%s" href="%s"><i class="bi bi-%s"></i> %s</a>',
        $active,
        htmlspecialchars($href),
        $icon,
        $label
    );
}
?>
<style>
.mednav-sidebar {
    width: 240px;
    min-height: 100vh;
    background: linear-gradient(160deg, #2d3a8c 0%, #764ba2 100%);
    color: #fff;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    z-index: 100;
}
.mednav-sidebar .brand {
    padding: 24px 20px 16px;
    border-bottom: 1px solid rgba(255,255,255,.15);
}
.mednav-sidebar .brand h6 { font-size: .75rem; opacity: .6; margin-bottom: 2px; }
.mednav-sidebar .brand h5 { font-size: 1rem; font-weight: 700; margin: 0; }
.mednav-sidebar .nav-link {
    color: rgba(255,255,255,.75);
    padding: 10px 20px;
    font-size: .88rem;
    display: flex;
    align-items: center;
    gap: 10px;
    border-radius: 0;
    transition: background .15s;
}
.mednav-sidebar .nav-link:hover,
.mednav-sidebar .nav-link.active {
    background: rgba(255,255,255,.15);
    color: #fff;
}
.mednav-sidebar .nav-section {
    padding: 8px 20px 4px;
    font-size: .7rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    opacity: .45;
    margin-top: 8px;
}
.mednav-footer {
    margin-top: auto;
    padding: 16px 20px;
    border-top: 1px solid rgba(255,255,255,.15);
    font-size: .8rem;
    opacity: .65;
}
.mednav-main { margin-left: 240px; }
</style>

<aside class="mednav-sidebar">
    <div class="brand">
        <h6>CAREAID SYSTEM</h6>
        <h5><i class="bi bi-heart-pulse-fill me-1"></i> Health Worker Portal</h5>
    </div>

    <nav class="mt-2 d-flex flex-column gap-1">
        <div class="nav-section">Overview</div>
        <?php echo _nav_link('health_worker_dashboard.php', 'speedometer2', 'Dashboard', $_nav_current); ?>

        <div class="nav-section">Seniors</div>
        <?php echo _nav_link('senior_list.php', 'person-lines-fill', 'Senior List', $_nav_current); ?>
        <?php echo _nav_link('create_senior_profile.php', 'person-plus-fill', 'Senior Registration', $_nav_current); ?>

        <div class="nav-section">Medical</div>
        <?php echo _nav_link('senior_checkup.php', 'activity', 'Senior Checkup', $_nav_current); ?>
        <?php echo _nav_link('health_guidance.php', 'journal-medical', 'Health Guidance', $_nav_current); ?>
        <?php echo _nav_link('illness_list.php', 'clipboard2-heart', 'List of Illness', $_nav_current); ?>
    </nav>

    <div class="mednav-footer">
        Logged in as <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'user'); ?></strong><br>
        <a href="../auth_module/logout.php" class="text-white-50 text-decoration-none mt-1 d-inline-block">
            <i class="bi bi-box-arrow-right"></i> Logout
        </a>
    </div>
</aside>
