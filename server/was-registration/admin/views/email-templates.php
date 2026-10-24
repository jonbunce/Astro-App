<?php defined( 'ABSPATH' ) || exit;

$notice  = '';
$templates = WAS_Notifications::default_templates();

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['was_email_nonce'] ) ) {
    check_admin_referer( 'was_save_email_templates', 'was_email_nonce' );

    // Handle reset
    foreach ( array_keys( $templates ) as $key ) {
        if ( isset( $_POST[ 'reset_' . $key ] ) ) {
            delete_option( 'was_email_subject_' . $key );
            delete_option( 'was_email_body_'    . $key );
            $notice = 'Template reset to default.';
            break;
        }
    }

    foreach ( array_keys( $templates ) as $key ) {
        $subject = sanitize_text_field( wp_unslash( $_POST[ 'subject_' . $key ] ?? '' ) );
        $body    = sanitize_textarea_field( wp_unslash( $_POST[ 'body_'    . $key ] ?? '' ) );
        if ( $subject ) update_option( 'was_email_subject_' . $key, $subject );
        if ( $body )    update_option( 'was_email_body_'    . $key, $body );
    }
    $notice = 'Email templates saved.';
}

// Load current values
$current = array();
foreach ( array_keys( $templates ) as $key ) {
    $tpl = WAS_Notifications::get_template( $key );
    $current[ $key ] = $tpl;
}

$active_key = sanitize_key( $_GET['tpl'] ?? array_key_first( $templates ) );
if ( ! isset( $templates[ $active_key ] ) ) $active_key = array_key_first( $templates );
?>
<div class="wrap was-admin-wrap">
    <h1>Email Templates</h1>
    <p class="description">Customise the emails sent by ASTRO. Use the placeholders shown under each template to insert dynamic values. Leave a field blank to use the built-in default.</p>

    <?php if ( $notice ) : ?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div>
    <?php endif; ?>

    <div class="was-email-tpl-layout">

        <?php /* Sidebar nav */ ?>
        <div class="was-email-tpl-nav">
            <?php foreach ( $templates as $key => $tpl ) : ?>
            <a href="?page=was-email-templates&tpl=<?php echo esc_attr($key); ?>"
               class="was-email-tpl-nav__item <?php echo $key === $active_key ? 'was-active' : ''; ?>">
                <?php echo esc_html( $tpl['label'] ); ?>
            </a>
            <?php endforeach; ?>
        </div>

        <?php /* Editor */ ?>
        <div class="was-email-tpl-editor">
            <?php $tpl = $current[ $active_key ]; $defaults = $templates[ $active_key ]; ?>
            <h2><?php echo esc_html( $tpl['label'] ); ?></h2>

            <div class="was-email-tpl-placeholders">
                <strong>Available placeholders:</strong>
                <?php foreach ( $defaults['placeholders'] as $ph ) : ?>
                <code class="was-ph-tag" title="Click to copy"><?php echo esc_html($ph); ?></code>
                <?php endforeach; ?>
            </div>

            <form method="post">
                <?php wp_nonce_field( 'was_save_email_templates', 'was_email_nonce' ); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="subject_<?php echo $active_key; ?>">Subject line</label></th>
                        <td>
                            <input type="text" id="subject_<?php echo $active_key; ?>"
                                   name="subject_<?php echo $active_key; ?>"
                                   class="large-text"
                                   value="<?php echo esc_attr( $tpl['subject'] ); ?>"
                                   placeholder="<?php echo esc_attr( $defaults['subject'] ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="body_<?php echo $active_key; ?>">Email body</label></th>
                        <td>
                            <textarea id="body_<?php echo $active_key; ?>"
                                      name="body_<?php echo $active_key; ?>"
                                      class="large-text"
                                      rows="14"
                                      placeholder="<?php echo esc_attr( $defaults['body'] ); ?>"><?php echo esc_textarea( $tpl['body'] ); ?></textarea>
                            <p class="description">Plain text only. Line breaks are preserved.</p>
                        </td>
                    </tr>
                </table>

                <div style="display:flex;gap:0.75rem;align-items:center;margin-top:1rem;">
                    <?php submit_button( 'Save template', 'primary', 'submit', false ); ?>
                    <button type="submit" name="reset_<?php echo esc_attr($active_key); ?>" class="button button-secondary"
                            onclick="return confirm('Reset this template to the built-in default?');">
                        Reset to default
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.was-email-tpl-layout { display: flex; gap: 1.5rem; margin-top: 1rem; align-items: flex-start; }
.was-email-tpl-nav { width: 220px; flex-shrink: 0; background: #fff; border: 1px solid #dde1e7; border-radius: 8px; overflow: hidden; }
.was-email-tpl-nav__item { display: block; padding: 0.75rem 1rem; font-size: 0.85rem; color: #333; text-decoration: none; border-bottom: 1px solid #f0f2f5; line-height: 1.4; }
.was-email-tpl-nav__item:last-child { border-bottom: none; }
.was-email-tpl-nav__item:hover { background: #f7f8fa; color: #1a3a5c; }
.was-email-tpl-nav__item.was-active { background: #1a3a5c; color: #fff; font-weight: 600; }
.was-email-tpl-editor { flex: 1; background: #fff; border: 1px solid #dde1e7; border-radius: 8px; padding: 1.5rem; }
.was-email-tpl-placeholders { background: #f7f8fa; border: 1px solid #e0e3e8; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
.was-ph-tag { background: #e8f0fe; color: #1a3a5c; border: 1px solid #bad0f8; border-radius: 4px; padding: 2px 6px; cursor: pointer; font-size: 0.8rem; }
.was-ph-tag:hover { background: #1a3a5c; color: #fff; }
@media (max-width: 900px) { .was-email-tpl-layout { flex-direction: column; } .was-email-tpl-nav { width: 100%; display: flex; flex-wrap: wrap; } .was-email-tpl-nav__item { flex: 1; min-width: 140px; border-right: 1px solid #f0f2f5; } }
</style>

<script>
// Click placeholder to copy/insert into focused textarea
document.querySelectorAll('.was-ph-tag').forEach(function(tag) {
    tag.addEventListener('click', function() {
        var text = this.textContent;
        var ta = document.querySelector('textarea[name^="body_"]');
        if (document.activeElement && document.activeElement.tagName === 'TEXTAREA') {
            ta = document.activeElement;
        }
        if (ta) {
            var start = ta.selectionStart;
            var end   = ta.selectionEnd;
            ta.value  = ta.value.substring(0, start) + text + ta.value.substring(end);
            ta.selectionStart = ta.selectionEnd = start + text.length;
            ta.focus();
        } else {
            navigator.clipboard?.writeText(text);
        }
    });
});
</script>
