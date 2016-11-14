<?php
namespace Reqres\Module\Zadarma;

use Reqres\Request;
use Reqres\Superglobals\POST;
use Reqres\Superglobals\SERVER;

trait Controller {

    // !!! нужно оставить возможность менять путь и IPшник
    // с этого адреса обычно приходят колбэки
    
    abstract function zadarma_get_secret();
    
    /**
     *
     * 
     *
     */
    function zadarma_index()
    {
        /*
        // получаем время последнего обращения к серверу
        // по-умолчанию это время последнего изменения файла zadarma_data.json
        // который хранится по указанному пути и который содержит в себе
        // общую информацию о текущем аккаунте 
        $time = $this-> model()-> zadarma_get_last_time();
        
        // если обращались последний раз дольше минуты назад
        if( time() - $time > 60 ){
        
            // обращаемся заново к серверу за основной информацией
            // и записываем ее в json файл
            // и сохраняем её в переменную
            $this-> zadarma_data = $this-> model()-> zadarma_get_common_info();
            
        } else {
            
            // иначе получаем старую информацию
            // сохраняем ее в переменную
            $this-> zadarma_data = $this-> model()-> zadarma_get_last_info();
            
        }
        
		// выводим главную страницу
        if(!$return) $this-> view()-> zadarma_main();
        */
        
    }
    
    

    /**
     *
     * Обработка входящих звонков
     *
     * На каждый входящий звонок сервис Zadarma отсылает 3 callback'a
     *  - в начале вызова
     *  - при внутреннем наборе
     *  - в конце вызова
     *
     */
    function zadarma_inbound()
    {

        // проверочный код для подтверждения скрипта задармой
        if (isset($_GET['zd_echo'])) exit($_GET['zd_echo']);        
        
        // получаем переменные
        // номер звонящего
        $this-> caller_id = POST::caller_id(); 
        // номер, на который позвонили
        $this-> called_did = POST::called_did();
        // время начала звонка
        $this-> call_datetime = POST::call_start() or die;
        // id звонка;
        $this-> pbx_call_id = POST::pbx_call_id();
        // (опциональный) внутренний номер
        $this-> internal = POST::internal();

        if(SERVER::REMOTE_ADDR() !== '185.45.152.42') die;
        
        $secret = $this-> zadarma_get_secret();

        // Signature is send only if you have your API key and secret
        $headers = getallheaders();
        // высчитываем подпись
        $signatureTest = base64_encode(hash_hmac('sha1', $this-> caller_id . $this-> called_did . $this-> call_datetime, $secret[1] ));
        // если подпись подошла
        if(!array_key_exists('Signature', $headers)) die;
        if($headers['Signature'] !== $signatureTest) die;
        
        // передаем в модель ключи
        Model::zadarma_client($secret);  

        switch(POST::event()){

            case 'NOTIFY_START' : 

                // прописав этот метод можно перенаправить клиента на интересующий номер автоматически
                if(method_exists($this, 'zadarma_inbound_start')) $this-> zadarma_inbound_start();
                
            break;
            case 'NOTIFY_INTERNAL' : 

                // прописав этот метод можно перенаправить клиента на интересующий номер автоматически
                if(method_exists($this, 'zadarma_inbound_internal')) $this-> zadarma_inbound_internal();
                
            break;
            case 'NOTIFY_END' : 

                // выжидаем паузу (вдруг аудиофайл еще не сохранился)                
                //sleep(5);
                
                if(isset($this-> zadarma_record_dir))
                try {

                    // получаем ссылку на запись разговора
                    $record = Model::zadarma_api_record(null, null, $this-> pbx_call_id);

                    // обновляем информацию о записи
                    list($this-> recordpath, $this-> recordname) = $this-> model()-> zadarma_save_record($this-> zadarma_record_dir, $record-> links, $this-> pbx_call_id);

                } catch(\Exception $e){

                    die;
                    // обновляем информацию о записи
                    //$this-> model()-> zadarma_save_record($e-> getMessage(), $this-> pbx_call_id);

                }

                if(method_exists($this, 'zadarma_inbound_end')) $this-> zadarma_inbound_end();

            break;
        }
        
        die;
    }
}
