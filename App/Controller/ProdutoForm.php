<?php

use CoffeeCode\Uploader\File;
use CoffeeCode\Uploader\Image;
use Library\Control\Page;
use Library\Database\Filter;
use Library\Database\Transaction;

/**
 * Implementação classe controller
 * High Cohesion - Divisão conforme responsabilidade
 *               - uso de demais classes para completar suas atividades
 *               - Poucos métodos
 */
class ProdutoForm extends Page
{
    public function __construct() 
    {
        parent::__construct();
        $this->template = $this->twig->load('produto-form.html');
    }
    
    public function index(): void
    {
        try {
            Transaction::open('self_menu');
            $categories = (new Categoria())->all(new Filter('ativo', '=', true));
            $data = [
                "item"=>["max_size"=>"max. " . ini_get("upload_max_filesize")],
                "categories" => $categories,
                "title" => "Novo Item"
            ];

            echo $this->template->render($data);
            Transaction::close();

        } catch (\Exception $e) {
            Transaction::rollback();
            echo json_encode(["status" => "error", "data" => message($e->getMessage(),'danger',true)]);
        }        
    }

    /**
     * Cria um novo objeto da classe Produto e realiza a persistencia
     * 
     * @param array $data
     * @return void
     */
    public function create($data): void
    {
        $response = new stdClass;
        try {

            /**
             * validações
             */
            $itemData = filter_var_array($data, FILTER_SANITIZE_STRING);           
            $this->validateForm($itemData);
            $pathImage = $this->validateImage();
            
            /**
             * persistência [Information Expert]
             */
            Transaction::open('self_menu');
            $item = new Produto();
            $item->fromArray($itemData);
            $item->url_image = isset($pathImage) ? $pathImage : $item->url_image;
            $item->store();
            $item->atualizaEstoque(); // Teste Observer
            Transaction::close();

            /**
             * Retorno GUI
             */
            $response->status = 'success';
            $response->data = message('Item salvo com sucesso', 'success', true);
            echo json_encode($response);
        } catch (\Exception $e) {
            Transaction::rollback();
            echo json_encode(["status" => "error", "data" => message($e->getMessage(),'danger',true)]);
        }
    }

    public function update($param)
    {
        $data = array();
        try {
            if (isset($param['id'])) {                
                /**
                 * Information Expert
                 * aciona a classe responsável por recuperar um Produto:
                 */
                Transaction::open('self_menu');
                $item = Produto::find($param['id']);            
                if ($item) {
                    $data["item"] = $item->toArray();
                }
                Transaction::close();

                /**
                 * envia os dados para a camada de visão:
                 */
                $data["item"]["max_size"] = "max. " . ini_get("upload_max_filesize");
                echo $this->template->render($data);
            }
        } catch (\Exception $e) {
            Transaction::rollback();
            echo json_encode(["status" => "error", "data" => $e->getMessage()]);
        }
    }

    /**
     * Valida o tipo de imagem e se a mesma foi informada
     *
     * @return void
     */
    private function validateImage()
    {
        // file image validation
        $upload = new Image( __DIR__ . "/../Storage", "images");
        $files = $_FILES;
        if (!empty($files['image'])) {
            $file = $files['image'];

            if ( empty($file["type"]) || !in_array($file["type"], $upload::isAllowed())) {
                $response["status"] = 'error';
                $response["data"] = message('Informe uma imagem válida!', "warning", true);
                echo json_encode($response);
                exit;
            } else {
                $uploaded = $upload->upload($file, pathinfo($file['name'], PATHINFO_FILENAME), 350);
                return str_replace('/var/www/arq-sis-projeto01', BASE_URL, $uploaded);
            }                
        }
    }

    /**
     * Validação campos formulário
     *
     * @param array $data
     * @return void
     */
    private function validateForm(array $data)
    {
        if (in_array("", $data)) {
            $response["status"] = 'error';
            $response["data"] = message(
                'Preencha os campos para criar um item', 'danger', true
            );
            echo json_encode($response);
            exit;
        } 
    }

}