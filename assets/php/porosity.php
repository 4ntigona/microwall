<?php
// Verificar se o usuário tem acesso poroso
function paywall_has_porosity_access($user_id) {
    $porosity_enabled = get_option('paywall_porosity_enabled', false);
    $porosity_limit = intval(get_option('paywall_porosity_limit', 0));
    
    if (!$porosity_enabled || $porosity_limit <= 0) {
        return false;
    }
    
    $access_count = get_user_meta($user_id, 'paywall_access_count', true);
    $access_count = intval($access_count);
    
    return $access_count < $porosity_limit;
}

// Verificar a permissão de acesso ao conteúdo
function paywall_check_content_access($content) {
    global $post;
    
    $selected_tags = get_option('paywall_selected_tags', array());
    
    if (empty($selected_tags) || is_user_logged_in() || current_user_can('administrator')) {
        return $content;
    }
    
    $tags = wp_get_post_tags($post->ID, array('fields' => 'ids'));
    
    $intersect = array_intersect($tags, $selected_tags);
    
    if (!empty($intersect) && paywall_has_porosity_access(get_current_user_id())) {
        $user_id = get_current_user_id();
        $access_count = get_user_meta($user_id, 'paywall_access_count', true);
        $access_count = intval($access_count);
        update_user_meta($user_id, 'paywall_access_count', $access_count + 1);
        return $content;
    }
    
    return '<p>Você precisa ser um usuário assinante para visualizar este conteúdo.</p>';
}

// Modificar a função de restrição de acesso no front-end
function paywall_modify_content_access() {
    add_filter('the_content', 'paywall_check_content_access');
}
add_action('wp', 'paywall_modify_content_access');

// Adicionar configurações de porosidade no painel de administração
function paywall_render_porosity_settings() {
    $porosity_enabled = get_option('paywall_porosity_enabled', false);
    $porosity_limit = get_option('paywall_porosity_limit', 0);
    ?>
    <h3>Configurar Porosidade</h3>
    <p>Defina as opções de porosidade do paywall:</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="paywall_save_porosity_settings">
        <?php wp_nonce_field('paywall_porosity_settings'); ?>
        
        <label for="paywall_porosity_enabled">
            <input type="checkbox" id="paywall_porosity_enabled" name="paywall_porosity_enabled" <?php checked($porosity_enabled); ?> />
            Permitir acesso poroso
        </label>
        <br>
        <label for="paywall_porosity_limit">
            Número máximo de itens permitidos sem estar logado:
            <input type="number" id="paywall_porosity_limit" name="paywall_porosity_limit" value="<?php echo $porosity_limit; ?>" min="0" />
        </label>
        
        <?php submit_button('Salvar', 'primary', 'submit', false); ?>
    </form>
    <?php
}

// Salvar as configurações de porosidade
function paywall_save_porosity_settings() {
    if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'paywall_porosity_settings')) {
        $porosity_enabled = isset($_POST['paywall_porosity_enabled']) ? true : false;
        $porosity_limit = intval($_POST['paywall_porosity_limit']);
        
        update_option('paywall_porosity_enabled', $porosity_enabled);
        update_option('paywall_porosity_limit', $porosity_limit);
        
        wp_safe_redirect(admin_url('admin.php?page=paywall-settings'));
        exit();
    } else {
        wp_die('Erro ao salvar as configurações de porosidade.');
    }
}
add_action('admin_post_paywall_save_porosity_settings', 'paywall_save_porosity_settings');



// Adicionar campo de configuração de porosidade nas configurações do plugin
function paywall_add_porosity_settings_fields() {
    add_settings_section('paywall_porosity_settings_section', 'Configurações de Porosidade', 'paywall_render_porosity_settings', 'paywall_settings');
    register_setting('paywall_settings', 'paywall_porosity_enabled');
    register_setting('paywall_settings', 'paywall_porosity_limit');
}
add_action('admin_init', 'paywall_add_porosity_settings_fields');

?>