<?php
// includes/sidebar.php
// Dynamic Sidebar template file with custom database colors and role-based sections
$userRole   = $_SESSION['user_role'] ?? '';
$isLoggedIn = isset($_SESSION['user_id']);
$isStaff    = in_array($userRole, ['admin', 'agency', 'agent'], true);
$isAgency   = in_array($userRole, ['admin', 'agency'], true);
$isAdmin    = $userRole === 'admin';
?>

<style>
/* 1. Base Sidebar Definition (Expanded Mode: 270px) */
#sidebar-wrapper {
    width: 270px !important;
    min-width: 270px !important;
    max-width: 270px !important;
    flex-shrink: 0;
    transition: width 0.3s ease-in-out, min-width 0.3s ease-in-out, max-width 0.3s ease-in-out;
    box-sizing: border-box;
    background-color: #212529 !important;
    z-index: 1000;
    overflow-x: hidden;
}

/* List item styles - Text wraps naturally to multiple lines when long */
#sidebar-wrapper .list-group-item {
    white-space: normal !important;
    line-height: 1.3;
    padding-top: 0.75rem;
    padding-bottom: 0.75rem;
    padding-left: 1.25rem;
    padding-right: 1.25rem;
    color: rgba(255, 255, 255, 0.8) !important;
    overflow-wrap: break-word;
    word-break: break-word;
    transition: padding 0.3s ease-in-out;
}

#sidebar-wrapper .list-group-item:hover {
    background-color: rgba(255, 255, 255, 0.08) !important;
    color: #fff !important;
}

#sidebar-wrapper .list-group-item i {
    flex-shrink: 0;
    width: 24px;
    text-align: center;
    font-size: 1.1rem;
}

/* Link text and section header transition rules */
#sidebar-wrapper .link-text,
#sidebar-wrapper .sidebar-header {
    opacity: 1;
    transition: opacity 0.2s ease-in-out;
}

/* 2. DESKTOP Behavior (Screens >= 768px)
   - Default: Expanded (270px) with full text labels
   - Hamburger clicked (adds .sidebar-collapsed to body): Background AND width shrink strictly to 70px (Icons only)
*/
@media (min-width: 768px) {
    body.sidebar-collapsed #sidebar-wrapper {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
    }

    body.sidebar-collapsed #sidebar-wrapper .link-text,
    body.sidebar-collapsed #sidebar-wrapper .sidebar-header,
    body.sidebar-collapsed #sidebar-wrapper hr {
        display: none !important;
    }

    body.sidebar-collapsed #sidebar-wrapper .list-group-item {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    body.sidebar-collapsed #sidebar-wrapper .list-group-item i {
        margin-right: 0 !important;
    }
}

/* 3. MOBILE / TABLET Behavior (Screens < 768px)
   - Default (window resize): Background AND width shrink strictly to 70px (Icons only)
   - Hamburger clicked (adds .sidebar-expanded to body): Background AND width expand to 270px AND reveal text
*/
@media (max-width: 767.98px) {
    #sidebar-wrapper {
        width: 70px !important;
        min-width: 70px !important;
        max-width: 70px !important;
    }

    #sidebar-wrapper .link-text,
    #sidebar-wrapper .sidebar-header,
    #sidebar-wrapper hr {
        display: none !important;
    }

    #sidebar-wrapper .list-group-item {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }

    #sidebar-wrapper .list-group-item i {
        margin-right: 0 !important;
    }

    /* Expanded state when clicking hamburger on mobile */
    body.sidebar-expanded #sidebar-wrapper {
        width: 270px !important;
        min-width: 270px !important;
        max-width: 270px !important;
    }

    body.sidebar-expanded #sidebar-wrapper .link-text,
    body.sidebar-expanded #sidebar-wrapper .sidebar-header {
        display: inline-block !important;
    }

    body.sidebar-expanded #sidebar-wrapper hr {
        display: block !important;
    }

    body.sidebar-expanded #sidebar-wrapper .list-group-item {
        justify-content: flex-start !important;
        padding-left: 1.25rem !important;
        padding-right: 1.25rem !important;
    }

    body.sidebar-expanded #sidebar-wrapper .list-group-item i {
        margin-right: 0.5rem !important;
    }
}

/* Prevents visual flickering artifacts during screen resize */
.resize-animation-stopper * {
    animation-duration: 0s !important;
    transition-duration: 0s !important;
}
</style>

<aside id="sidebar-wrapper" class="border-end border-secondary bg-dark text-white">
    <div class="list-group list-group-flush py-3">
        
        <!-- SECTION 1: Public & General Users -->
        <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('general', 'General'); ?></div>
        
        <a href="/index.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('home', 'Home'); ?>">
            <i class="fa-solid fa-house me-2 text-primary"></i>
            <span class="link-text"><?php echo __('home', 'Home'); ?></span>
        </a>
        <a href="/submit.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('submit_ticket', 'Submit Ticket'); ?>">
            <i class="fa-solid fa-plus-circle me-2 text-success"></i>
            <span class="link-text"><?php echo __('submit_ticket', 'Submit Ticket'); ?></span>
        </a>
        <a href="/track.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('track_ticket', 'Track Ticket'); ?>">
            <i class="fa-solid fa-magnifying-glass me-2 text-info"></i>
            <span class="link-text"><?php echo __('track_ticket', 'Track Ticket'); ?></span>
        </a>

        <?php if ($isLoggedIn): ?>
            <a href="/profile.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('account_settings', 'Account Settings'); ?>">
                <i class="fa-solid fa-user-gear me-2 text-warning"></i>
                <span class="link-text"><?php echo __('account_settings', 'Account Settings'); ?></span>
            </a>
        <?php endif; ?>

        <?php if ($isStaff): ?>
            <!-- SECTION 2: Staff Workspace (Admin, Agency, Agent) -->
            <hr class="border-secondary my-2">
            <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('staff_workspace', 'Staff Workspace'); ?></div>
            
            <a href="/admin/dashboard.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('dashboard', 'Dashboard'); ?>">
                <i class="fa-solid fa-chart-line me-2 text-primary"></i>
                <span class="link-text"><?php echo __('dashboard', 'Dashboard'); ?></span>
            </a>
            <a href="/admin/tickets.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('tickets', 'Tickets'); ?>">
                <i class="fa-solid fa-ticket me-2 text-warning"></i>
                <span class="link-text"><?php echo __('tickets', 'Tickets'); ?></span>
            </a>

            <?php if ($isAgency): ?>
                <!-- SECTION 3: Agency & Referral Management (Admin & Agency) -->
                <hr class="border-secondary my-2">
                <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('agency_management', 'Agency Management'); ?></div>
                
                <a href="/admin/agencies.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('agencies_and_agents', 'Agencies & Agents'); ?>">
                    <i class="fa-solid fa-building-user me-2 text-info"></i>
                    <span class="link-text"><?php echo $isAdmin ? __('manage_agencies', 'Manage Agencies') : __('my_agents', 'My Agents'); ?></span>
                </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <!-- SECTION 4: System Administration (Admin Only) -->
                <hr class="border-secondary my-2">
                <div class="px-3 text-uppercase text-info-emphasis small fw-bold mb-2 sidebar-header"><?php echo __('system_administration', 'System Administration'); ?></div>
                
                <a href="/admin/users.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('all_users', 'All Users'); ?>">
                    <i class="fa-solid fa-users-gear me-2 text-danger"></i>
                    <span class="link-text"><?php echo __('all_users', 'All Users'); ?></span>
                </a>
                <a href="/admin/categories.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('categories', 'Categories'); ?>">
                    <i class="fa-solid fa-folder me-2 text-warning"></i>
                    <span class="link-text"><?php echo __('categories', 'Categories'); ?></span>
                </a>
                <a href="/admin/settings.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('settings', 'Settings'); ?>">
                    <i class="fa-solid fa-sliders me-2 text-secondary"></i>
                    <span class="link-text"><?php echo __('settings', 'Settings'); ?></span>
                </a>
                <a href="/admin/rate_limits.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('rate_limits', 'Rate Limits'); ?>">
                    <i class="fa-solid fa-gauge-high me-2 text-success"></i>
                    <span class="link-text"><?php echo __('rate_limits', 'Rate Limits'); ?></span>
                </a>
                <a href="/admin/translations.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('translations', 'Translations'); ?>">
                    <i class="fa-solid fa-language me-2 text-info"></i>
                    <span class="link-text"><?php echo __('translations', 'Translations'); ?></span>
                </a>
                <a href="/admin/update.php" class="list-group-item list-group-item-action d-flex align-items-center bg-transparent border-0 text-white" title="<?php echo __('system_updates', 'System Updates'); ?>">
                    <i class="fa-solid fa-arrows-rotate me-2 text-primary"></i>
                    <span class="link-text"><?php echo __('system_updates', 'System Updates'); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>
        
    </div>
</aside>

<script>
// Prevents flickering animations and layout artifacts during window resize
(function() {
    let resizeTimer;
    window.addEventListener("resize", () => {
        document.body.classList.add("resize-animation-stopper");
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            document.body.classList.remove("resize-animation-stopper");
        }, 400);
    });
})();
</script>
