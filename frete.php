<?php
/*
Plugin Name: Calcule o frete
Description: Calcule o frete ou cadastre um valo fixo
Version: 1.0
Author: Jader Adriel
*/

function meu_painel_frete() {
    add_menu_page(
        'Configurações do Frete', // Título da página
        'Config Frete', // Título do menu
        'manage_options', // Permissão necessária
        'configuracoes-frete', // Slug da página
        'painel_frete_conteudo', // Função para exibir o conteúdo
        'dashicons-admin-generic', // Ícone do menu
        20 // Posição no menu
    );
    add_submenu_page(
        'configuracoes-frete', // Slug da página pai
        'frete locais', // Título da página
        'Gerenciar van fretes', // Título do menu
        'manage_options', // Permissões necessárias
        'meu-painel-secundario', // Slug da página
        'pagina_gerenciar_vans' // Função que irá exibir o conteúdo da página
    );
}
add_action('admin_menu', 'meu_painel_frete');

function painel_frete_conteudo() {
    // Verifica se o formulário foi enviado
    if (isset($_POST['submit_frete'])) {
        update_option('taxa_frete', sanitize_text_field($_POST['taxa_frete']));
        update_option('cep_origem', sanitize_text_field($_POST['cep_origem']));
        echo '<div class="notice notice-success is-dismissible">Configurações atualizadas com sucesso!</div>';
    }

    $taxa_frete = get_option('taxa_frete', '10');
    $cep_origem = get_option('cep_origem', '00000-000');
    ?>

    <h1>Configurações do Plugin de Frete</h1>
    <form method="post">
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="taxa_frete">Taxa de Frete (em porcentagem):</label>
                </th>
                <td>
                    <input type="number" name="taxa_frete" id="taxa_frete" value="<?php echo esc_attr($taxa_frete); ?>">
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="cep_origem">CEP de Origem:</label>
                </th>
                <td>
                    <input type="text" name="cep_origem" id="cep_origem" value="<?php echo esc_attr($cep_origem); ?>">
                </td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit_frete" id="submit_frete" class="button button-primary" value="Salvar Alterações">
        </p>
    </form>
    <?php
}

function pagina_gerenciar_vans() {
    criar_frete_van();
    mostrar_tabela_vans();
}

function criar_frete_van() {
    ?>
    <h1>Adicione fretes para serem entregues com vans</h1>
    <form action="" method="post">
        <p>
            <label for="cep">CEP:</label>
            <input type="text" id="cep" name="cep" required>
        </p>
        <p>
            <label for="valor_frete">Valor do frete:</label>
            <input type="number" id="valor_frete" name="valor_frete" required>
        </p>
        <p>
            <label for="valor_frete">Nome da van:</label>
            <input type="text" id="nome" name="nome">
        </p>
        <p>
            <label for="valor_frete">Contato da van:</label>
            <input type="text" id="contato" name="contato">
        </p>
        <input type="submit" name="submit" value="Salvar">
    </form>
    
    <?php
    if(isset($_POST['submit'])) {

        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_name = 'frete_van';

        $sql = "CREATE TABLE $table_name (
        CEP varchar(11) NOT NULL,
        contato varchar(255),
        nome varchar(255),
        valor_frete INTEGER NOT NULL,
        PRIMARY KEY  (CEP)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        $cep = sanitize_text_field($_POST['cep']);
        $valor_frete = sanitize_text_field($_POST['valor_frete']);
        $contato = sanitize_text_field($_POST['contato']);
        $nome = sanitize_text_field($_POST['nome']);

        $data = array(
            'CEP' => $cep,
            'valor_frete' => $valor_frete,
            'contato' => $contato,
            'nome' => $nome,
        );

        $wpdb->insert($table_name, $data);
    }
}

function mostrar_tabela_vans() {
    ?> 
    <h2>Vans cadastradas</h2>
        <table>
    <thead>
        <tr>
        <th>Nome</th>
        <th>CEP</th>
        <th>Valor do frete</th>
        <th>Contato</th>
        </tr>
    </thead>
    <tbody>
        <?php
        global $wpdb;
        $table_name = 'frete_van';
        $rows = $wpdb->get_results("SELECT * FROM $table_name");
        foreach($rows as $row) {
        ?>
        <tr>
        <td><?php echo $row->nome; ?></td>
        <td><?php echo $row->CEP; ?></td>
        <td><?php echo $row->valor_frete; ?></td>
        <td><?php echo $row->contato; ?></td>
        </tr>
        <?php
        }
        ?>
    </tbody>
    </table>
    <?php
}

function search_frete_func() {
    
    ?>
    <form action="." method="POST">
        <h2>Cálculo de Frete</h2>
        <p>
            <label for="cep_destino">CEP de destino:</label>
            <input type="text" id="cep_destino" name="cep_destino">
        </p>
        <p>
            <label for="tipo_entrega">Escolha o tipo de entrega:</label>
            <select id="tipo_entrega" name="tipo_entrega">
                <option value="40010">SEDEX</option>
                <option value="41106">PAC</option>
            </select>
        </p>
        <p>
            <input type="submit" value="Calcular Frete">
        </p>
    </form>
    <?php

    if ( $_GET ) return;

    
    $cep_destino = $_POST['cep_destino'];
    $tipo_do_frete = $_POST['tipo_entrega'];
    $frete = pegar_frete_registrado_func($cep_destino);

    if ( $frete ) {
        return $frete;
    }

    $frete = pegar_frete_correios_func($cep_destino, $tipo_do_frete);
    
    return $frete;
    
}

function pegar_frete_registrado_func($cep) {
    global $wpdb;

    $sql = "SELECT `CEP`, `valor_frete` FROM `frete_van` WHERE `CEP` LIKE " . $cep . ";";

    $result = $wpdb->get_results($sql);

    if ($result[0]) {
        $html  = "<p> Cep: ". $cep ."</p>";
        $html .= "<p> Valor: ". calc_valor_com_taxa($result[0]->valor_frete) ." R$</p>";

        return $html;
    }

    return FALSE; 
}

function pegar_frete_correios_func($cep_destino, $tipo_do_frete) {
    $cep_origem    = get_option('cep_origem', '00000-000');
    $peso          = 2;
    $valor         = 100;
    $altura        = 6;
    $largura       = 20;
    $comprimento   = 20;


    $url = "http://ws.correios.com.br/calculador/CalcPrecoPrazo.aspx?";
    $url .= "nCdEmpresa=";
    $url .= "&sDsSenha=";
    $url .= "&sCepOrigem=" . $cep_origem;
    $url .= "&sCepDestino=" . $cep_destino;
    $url .= "&nVlPeso=" . $peso;
    $url .= "&nVlLargura=" . $largura;
    $url .= "&nVlAltura=" . $altura;
    $url .= "&nCdFormato=1";
    $url .= "&nVlComprimento=" . $comprimento;
    $url .= "&sCdMaoProria=n";
    $url .= "&nVlValorDeclarado=" . $valor;
    $url .= "&sCdAvisoRecebimento=n";
    $url .= "&nCdServico=" . $tipo_do_frete;
    $url .= "&nVlDiametro=0";
    $url .= "&StrRetorno=xml";


    $xml = simplexml_load_file($url);

    $frete =  $xml->cServico;

    if ( calc_valor_com_taxa($frete->Valor) < 0) return "cep não válido";

    $html  = "<p> Cep: ". $cep_destino ."</p>";
    $html .= "<p> Valor: ". calc_valor_com_taxa($frete->Valor) ." R$</p>";
    $html .= "<p> Prazo: ".$frete->PrazoEntrega." dias</p>";

    return $html;
}

function calc_valor_com_taxa($valor) {
    $taxa = get_option('taxa_frete', 0);
    return $valor + $valor / 100 * $taxa;
}

add_shortcode('frete', 'search_frete_func');

?>

