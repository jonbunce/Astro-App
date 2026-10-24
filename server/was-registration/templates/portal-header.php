<?php defined( 'ABSPATH' ) || exit; ?>
<div class="was-portal-wrap">

    <div class="was-portal-header">
        <div class="was-portal-header__left">
            <div class="astro-portal-logo-wrap">
                <img src="<?php echo esc_url( was_portal_logo_url() ); ?>" class="astro-portal-logo" alt="ASTRO">
            </div>
            <?php $support_email = get_option( 'was_support_email', 'jon@wombourneallstars.co.uk' ); ?>
            <p class="was-portal-user">
                <?php echo esc_html( $user->display_name ); ?>
                <?php
                $role_labels = array( 'parent' => 'Parent', 'coach' => 'Coach', 'treasurer' => 'Treasurer', 'committee' => 'Committee', 'registration_officer' => 'Registration' );
                $display_roles = array_map( function($r) use ($role_labels) {
                    return $role_labels[$r] ?? ucfirst($r);
                }, array_filter( $roles, function($r) use ($role_labels) { return isset($role_labels[$r]); } ) );
                if ( $is_admin ) $display_roles[] = 'Admin';
                foreach ( $display_roles as $rl ) :
                ?>
                <span class="was-role-badge was-role-badge--<?php echo esc_attr( strtolower($rl) ); ?>"><?php echo esc_html( $rl ); ?></span>
                <?php endforeach; ?>
                <?php if ( $support_email ) : ?>
                <a href="mailto:<?php echo esc_attr( $support_email ); ?>" class="stars-support-link">Contact support</a>
                <?php endif; ?>
            </p>
        </div>
        <button type="button" id="was-logout-btn" class="was-btn was-btn--ghost was-btn--sm">Sign out</button>
    </div>

    <?php if ( $show_parent || $show_coach || $show_treasurer || $show_social_media || ! empty( $show_team_news_tab ) || ! empty( $show_messages ) || ! empty( $show_registrations ) || ! empty( $show_interest ) ) : ?>
    <div class="was-portal-nav-wrap">
    <button type="button" class="was-nav-arrow was-nav-arrow--left is-hidden" aria-label="Scroll tabs left">&lsaquo;</button>
    <button type="button" class="was-nav-arrow was-nav-arrow--right" aria-label="Scroll tabs right">&rsaquo;</button>
    <nav class="was-portal-nav">
        <?php if ( $show_parent )    : ?><a href="#was-section-parent"    class="was-portal-nav__tab was-active">My children</a><?php endif; ?>
        <?php if ( $show_parent )    : ?><a href="#was-section-profile"   class="was-portal-nav__tab">My profile</a><?php endif; ?>
        <?php if ( $show_coach )     : ?><a href="#was-section-coach"     class="was-portal-nav__tab<?php echo $show_parent ? '' : ' was-active'; ?>"><?php echo ! empty( $coach_all_teams ) ? 'Teams' : 'My teams'; ?></a><?php endif; ?>
        <?php if ( $show_treasurer )    : ?><a href="#was-section-treasurer"   class="was-portal-nav__tab">Payments</a><?php endif; ?>
        <?php if ( $show_social_media ) : ?><a href="#was-section-social-media" class="was-portal-nav__tab">Photo &amp; Media</a><?php endif; ?>
        <?php if ( ! empty( $show_team_news_tab ) ) : ?><a href="#was-section-team-news" class="was-portal-nav__tab">Team News</a><?php endif; ?>
        <?php if ( ! empty( $show_messages ) ) : ?><a href="#was-section-messages" class="was-portal-nav__tab">Messages</a><?php endif; ?>
        <?php if ( ! empty( $show_registrations ) ) : ?><a href="#was-section-registrations" class="was-portal-nav__tab<?php echo ( $show_parent || $show_coach ) ? '' : ' was-active'; ?>">Players</a><?php endif; ?>
        <?php if ( ! empty( $show_interest ) ) : ?><a href="#was-section-interest" class="was-portal-nav__tab<?php echo ( $show_parent || $show_coach || ! empty( $show_registrations ) ) ? '' : ' was-active'; ?>">Interest List</a><?php endif; ?>
    </nav>
    </div>
    <?php endif; ?>
