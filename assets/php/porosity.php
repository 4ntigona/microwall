<?php
// Adicionar configurações de porosidade no painel de administração
function microwall_render_porosity_settings() {
    $porosity_enabled = get_option('microwall_porosity_enabled', false);
    $porosity_limit = get_option('microwall_porosity_limit', 0);
    ?>
    <h3>Configurar Porosidade</h3>
    <p>Defina as opções de porosidade do microwall:</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="microwall_save_porosity_settings">
        <?php wp_nonce_field('microwall_porosity_settings'); ?>
        
        <label for="microwall_porosity_enabled">
            <input type="checkbox" id="microwall_porosity_enabled" name="microwall_porosity_enabled" <?php checked($porosity_enabled); ?> />
            Permitir acesso poroso
        </label>
        <label for="microwall_porosity_limit">
            Número máximo de itens permitidos sem estar logado:
            <input type="number" id="microwall_porosity_limit" name="microwall_porosity_limit" value="<?php echo $porosity_limit; ?>" min="0" />
        </label>
        <label for="microwall_porosity_message">
            Mensagem:
            <input type="text" id="microwall_porosity_message" name="microwall_porosity_message" value="<?php echo $porosity_message; ?>" />
        </label>
        
        <?php submit_button('Salvar', 'primary', 'submit', false); ?>
    </form>
    <?php
}

// Salvar as configurações de porosidade
function microwall_save_porosity_settings() {
    if (isset($_POST['_wpnonce']) && wp_verify_nonce($_POST['_wpnonce'], 'microwall_porosity_settings')) {
        $porosity_enabled = isset($_POST['microwall_porosity_enabled']) ? true : false;
        $porosity_limit = intval($_POST['microwall_porosity_limit']);
        $porosity_message = sanitize_text_field($_POST['microwall_porosity_message']);
        
        update_option('microwall_porosity_enabled', $porosity_enabled);
        update_option('microwall_porosity_limit', $porosity_limit);
        update_option('microwall_porosity_message', $porosity_message);
        
        wp_safe_redirect(admin_url('admin.php?page=microwall-settings'));
        exit();
    } else {
        wp_die('Erro ao salvar as configurações de porosidade.');
    }
}
add_action('admin_post_microwall_save_porosity_settings', 'microwall_save_porosity_settings');



// Adicionar campo de configuração de porosidade nas configurações do plugin
function microwall_add_porosity_settings_fields() {
    add_settings_section('microwall_porosity_settings_section', 'Configurações de Porosidade', 'microwall_render_porosity_settings', 'microwall_settings');
    register_setting('microwall_settings', 'microwall_porosity_enabled');
    register_setting('microwall_settings', 'microwall_porosity_limit');
    register_setting('microwall_settings', 'microwall_porosity_message');
}
add_action('admin_init', 'microwall_add_porosity_settings_fields');

// Função para atualizar o contador de visualizações do usuário não logado
function microwall_update_user_view_count() {
    $selected_tags = get_option('microwall_selected_tags', array());
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    // Verificar se o usuário não está logado
    if (!is_user_logged_in() && !in_array('subscriber', $current_user->roles)) {
        $cookie_name = 'microwall_view_count';
        $view_count = isset($_COOKIE[$cookie_name]) ? intval($_COOKIE[$cookie_name]) : 0;

        // Verificar se o usuário está visualizando um conteúdo com as tags configuradas
        if (has_tag($selected_tags)) {
            // Aumentar o contador de visualizações
            $view_count++;
            setcookie($cookie_name, $view_count, time() + (3600 * 24), COOKIEPATH, COOKIE_DOMAIN);
        }
    }
}
add_action('wp', 'microwall_update_user_view_count');

// Função para obter o contador de visualizações do usuário não logado
function microwall_get_user_view_count() {
    $cookie_name = 'microwall_view_count';
    return isset($_COOKIE[$cookie_name]) ? intval($_COOKIE[$cookie_name]) : 0;
}


?>