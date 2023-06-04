<?php

// Filtrar o conteúdo para aplicar a restrição de acesso
function paywall_filter_content($content) {
    global $post;

    // Verificar se o usuário é um assinante ativo ou um administrador
    $user = wp_get_current_user();
    $is_subscriber = in_array('subscriber', $user->roles);
    $is_admin = current_user_can('administrator');

    // Verificar se o usuário é um assinante ativo ou um administrador antes de aplicar a restrição
    if (($is_subscriber && paywall_is_subscription_active($user->ID)) || $is_admin) {
        return $content; // Exibir o conteúdo normalmente para assinantes ativos e administradores
    }

    // Verificar se o post tem uma ou mais tags configuradas no Paywall
    $restricted_tags = get_option('paywall_selected_tags', array());
    $post_tags = get_the_tags($post->ID);
    $porosity_enabled = get_option('paywall_porosity_enabled', false);
    $porosity_limit = get_option('paywall_porosity_limit', 0);
    // get_option('paywall_porosity_message', ''), if empty, use "Você atingiu o limite de {porosity_limit} visualizações gratuitas. Faça login ou <a href='https://loja.amarello.com.br/collections/revista' target='_blank'>assine</a> para continuar lendo." escaping the html
    $porosity_message = get_option('paywall_porosity_message', 'Você atingiu o limite de ' . $porosity_limit . ' visualizações gratuitas. Faça login ou <a href="https://loja.amarello.com.br/collections/revista" target="_blank">assine</a> para continuar lendo.');
    // use wp_kses_post to allow html tags
    $porosity_message = wp_kses_post($porosity_message);

    $view_count = paywall_get_user_view_count();

    if (!empty($post_tags) && !empty($restricted_tags)) {
        if ($porosity_enabled && $view_count > ($porosity_limit * 2)) {
            foreach ($post_tags as $tag) {
                if (in_array($tag->term_id, $restricted_tags)) {
                    // Se o post tiver uma das tags restritas, exibir apenas o primeiro parágrafo e o formulário de login
                    $first_paragraph = '';
                    $paragraphs = explode('</p>', $content);
                    if (!empty($paragraphs)) {
                        $first_paragraph = $paragraphs[0] . '</p>';
                    }

                    $first_paragraph .= '<p>' . $porosity_message . '</p>';

                    // Formulário de login
                    $login_form = wp_login_form(array(
                        'echo' => false
                    ));

                    return $first_paragraph . $login_form;
                }
            }
        }
    }

    return $content; // Exibir o conteúdo normalmente para posts sem tags restritas
}
add_filter('the_content', 'paywall_filter_content');


?>