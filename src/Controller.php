<?php
namespace Reqres\Module\Zadarma;

use Reqres\Request;
use Reqres\Superglobals\GET;
use Reqres\Superglobals\POST;
use Reqres\Superglobals\SERVER;

trait Controller {

    abstract function mod_zadarma_get_secret();
    
    /**
     *
     * 
     *
     */
    function mod_zadarma_index()
    {
        /*
        // получаем время последнего обращения к серверу
        // по-умолчанию это время последнего изменения файла zadarma_data.json
        // который хранится по указанному пути и который содержит в себе
        // общую информацию о текущем аккаунте 
        $time = $this-> model()-> mod_zadarma_get_last_time();
        
        // если обращались последний раз дольше минуты назад
        if( time() - $time > 60 ){
        
            // обращаемся заново к серверу за основной информацией
            // и записываем ее в json файл
            // и сохраняем её в переменную
            $this-> mod_zadarma_data = $this-> model()-> mod_zadarma_get_common_info();
            
        } else {
            
            // иначе получаем старую информацию
            // сохраняем ее в переменную
            $this-> mod_zadarma_data = $this-> model()-> mod_zadarma_get_last_info();
            
        }
        
		// выводим главную страницу
        if(!$return) $this-> view()-> mod_zadarma_main();
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
    function mod_zadarma_inbound()
    {

        // проверочный код для подтверждения скрипта задармой
        if(isset($_GET['zd_echo'])) exit($_GET['zd_echo']);
        //if(isset(GET::zd_echo()) exit(GET::zd_echo());
        
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
        // (опциональный) набранный номер при исходящих звонках
        $this-> destination = POST::destination();

        if(SERVER::REMOTE_ADDR() !== '185.45.152.42') die;
        
        // получаем секретную пару, которая должна возвращаться методом
        $secret = $this-> mod_zadarma_get_secret();
        $headers = getallheaders();

        // высчитываем подпись
        $signatureTest = $this-> destination
            // при исходящих звонках
            ? base64_encode(hash_hmac('sha1', $this-> internal . $this-> destination  . $this-> call_datetime, $secret[1] ))
            // при входящих звонках
            : base64_encode(hash_hmac('sha1', $this-> caller_id . $this-> called_did . $this-> call_datetime, $secret[1] ));

        // сверяем подпись
        if(!array_key_exists('Signature', $headers)) die;
        if($headers['Signature'] !== $signatureTest) die;
        
        // передаем в модель ключи
        Model::mod_zadarma_client($secret);  

        // получаем событие
        $event = POST::event();
        // прописываем обработчики для каждого события
        $methods = [
            'NOTIFY_START'      => 'mod_zadarma_inbound_start',
            'NOTIFY_INTERNAL'   => 'mod_zadarma_inbound_internal',
            'NOTIFY_END'        => 'mod_zadarma_inbound_end',
            'NOTIFY_OUT_END'    => 'mod_zadarma_outbound_end',
            'NOTIFY_OUT_START'  => 'mod_zadarma_outbound_start',
        ];

        // у нас завершающее событие, то сохраняем аудиофайл
        if(in_array($event, ['NOTIFY_END', 'NOTIFY_OUT_END'])){

            // при завершенном звонке у нас появляются дополнительная информация о вызове
            $rus_disposition = [
                'answered' => 'разговор',
                'busy' => 'занято',
                'cancel' => 'отменен',
                'no answer' => 'без ответа',
                'failed' => 'не удался',
                'no money' => 'нет средств, превышен лимит',
                'unallocated number' => 'номер не существует',
                'no limit' => 'превышен лимит',
                'no day limit' => 'превышен дневной лимит',
                'line limit' => 'превышен лимит линий',
                'no money, no limit' => 'превышен лимит',
            ];

            // длительность в секундах
            $this-> duration = POST::duration();
            // состояние звонка
            $this-> disposition = POST::disposition();
            // состояние звонка по-русски
            $this-> disposition_rus = array_key_exists($this-> disposition, $rus_disposition) ? $rus_disposition[$this-> disposition] : '';
            // код статуса звонка Q.931;
            $this-> status_code = POST::status_code(); 
            // 1 - есть запись звонка, 0 - нет записи;
            $this-> is_recorded = POST::is_recorded() === '1';
            // id звонка с записью
            $this-> call_id_with_rec = POST::call_id_with_rec();


            // рекомендуем загружать файл записи не ранее чем через 40 секунд после уведомления т.к. для сохранения файла записи нужно время
            sleep(40);

            if(isset($this-> mod_zadarma_record_dir))
            try {

                // получаем ссылку на запись разговора
                $record = Model::mod_zadarma_api_record(null, null, $this-> pbx_call_id);
                // скачиваем запись, сохраяняя ее в указанной папке
                list($this-> recordpath, $this-> recordname) = $this-> model()-> mod_zadarma_save_record($this-> mod_zadarma_record_dir, $record-> links, $this-> pbx_call_id);

            } catch(\Exception $e){

                // обновляем информацию о записи

                // останавливаем скрипт
                die;

            }            
        }

        // выполняем обработчики
        if(array_key_exists($event, $methods))
            // если обработчик существует
            if(method_exists($this, $methods[$event])) 
                // запускаем его
                $this-> { $methods[$event] }();

        die;
    }
}