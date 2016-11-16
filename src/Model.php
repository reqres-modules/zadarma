<?php
namespace Reqres\Module\Zadarma;

trait Model {
    
    
    static $mod_zadarma_client;
    
    
    static function mod_zadarma_client($keys)
    {
        
        if(!isset(static::$mod_zadarma_client))
            static::$mod_zadarma_client = new \Zadarma_API\Client($keys[0], $keys[1]);        
        
    }

    /**
     *
     * Выполняем запрос к сервису Zadarma через API
     *
     */
    static function mod_zadarma_api()
	{
		
        $args = func_get_args();

        if(!isset(static::$mod_zadarma_client)) throw new \Exception('Not authorized');
        
        $result = call_user_func_array([static::$mod_zadarma_client, 'call'], $args);
        
        $result = json_decode($result);

        if(!$result || !array_key_exists('status', $result)) throw new \Exception('Zadarma API "'. $args[0]. '" method error.');

        // если результат пришел правильный
        if($result-> status == 'success') {
            
            // возвращаем его
            return $result;
            
        }

        // бросаем ошибку
        throw new \Exception($result-> message);
                 
    }
    
    
    /**
     * 
     * Скачиваем аудиозапись
     *
     */       
    function mod_zadarma_save_record($basedir, $link, $call_id, $basename = null)
    {

        if(!$basename) $basename = basename($link[0]);
        if(!$basename) $basename = md5(uniqid());

        if(!is_dir($basedir)) mkdir($basedir);

        // скачиваем файл
        file_put_contents($basedir.DIRECTORY_SEPARATOR.$basename, file_get_contents($link[0]));           

        return [$basedir.DIRECTORY_SEPARATOR.$basename, $basename];

    }
    

    /**
     * 
     * баланс пользователя
     *
     */
    static function mod_zadarma_api_balance()
    {
    
        /*
        {
            "status":"success",
            "balance":10.34,
            "currency":"USD"
        }
        */
        return static::mod_zadarma_api('/v1/info/balance/');
        
    }
    
    
    /**
     * 
     * стоимость звонка с учетом текущего тарифа пользователя
     *
     */    
    static function mod_zadarma_api_prise($number, $caller_id = null)
    {
        /*
        {
            "status":"success",
            "info":{
                "prefix":"4420",
                "description":"United Kingdom, London",
                "price":0.009,"
                "currency":"USD"
            }
        }
        */
        return static::mod_zadarma_api('/v1/info/price/', [
            'number' => $number, //'48721000000',
            'caller_id' => $caller_id //'49100000000' // optional
        ]);
        
    }
    
    
    /**
     * 
     * информация о текущем тарифе пользователя.
     *
     */
    static function mod_zadarma_api_tariff()
    {
    
        /*
		{
            "status":"success",
            "info": {
                "tariff_id":5, 									ID текущего тарифа пользователя;
                "tariff_name":"Standart, special",				наименование текущего тарифа пользователя;
                "is_active":false,								активен или не активен текущий тариф;
                "cost":0,										стоимость тарифа;
                "currency":USD,									валюта тарифа;
                "used_seconds":1643,							количество использованных секунд тарифа;
                "used_seconds_mobile":34,						количество использованных секунд тарифа на мобильные телефоны;
                "used_seconds_fix":726,							количество использованных секунд тарифа на стационарные телефоны;
                "tariff_id_for_next_period":5,					ID тарифа пользователя на следующий период;			
                "tariff_for_next_period":Standart, special		наименование тарифа пользователя на следующий период. 
            }
        }
        */
        return static::mod_zadarma_api('/v1/tariff/');
      
    }
      
    /**
     * 
     * отображение внутренних номеров АТС. 
     *
     */
    static function mod_zadarma_api_internal()
    {
    
        /*
        {
            "status":"success",
            "pbx_id":1234,				id ATC пользователя;
            "numbers": [				список внутренних номеров. 
                100,
                101,
                ...
            ]
        }
        */
        return static::mod_zadarma_api('/v1/pbx/internal/');
        
    }    

    
    /**
     * 
     * online-статус внутреннего номера АТС
     *
     * @param number – номер телефона
     *
     */    
    static function mod_zadarma_api_internal_status($number)
    {
        /*
        {
            "status":"success",
            "pbx_id":1234,				АТС-id;
            "number":100,				внутренний номер АТС;
            "is_online":"false"			online-статус (true|false)
        }
        */
        return static::mod_zadarma_api('/v1/pbx/internal/'.(int) $number.'/status/');
        
    }
    
    
    /**
     * 
     * запрос на callback
     *
     * @param to – номер телефона или SIP, которому звонят;
     * @param from – ваш номер телефона или SIP, или внутренний номер АТС, или номер сценария АТС, на который вызывается callback;
     * @param sip (опционально) – номер SIP-пользователя или внутренний номер АТС (например 100), через который произойдет звонок. 
     *            Будет использован CallerID этого номера, в статистике будет отображаться данный номер SIP/АТС, 
     *            если для указанного номера включена запись звонков либо префиксы набора, они также будут задействованы;
     * @param predicted (опционально) – если указан этот флаг, то запрос является предикативным (система изначально звонит 
     *            на номер "to" и только если ему дозванивается, соединяет с вашим SIP либо телефонным номером);     
     */    
    static function mod_zadarma_api_callback($to, $from, $sip = null, $predicted = false)
    {
        /*
        {
            "status":"success",
            "from": 442037691880,
            "to": 442037691881,
            "time": 1435573082
        }
        */
        return static::mod_zadarma_api('/v1/request/callback/', [
            'from' => $from,
            'to' => $to,
            'sip' => $sip,
            'predicted' => $predicted,
        ]);
        
    }
    
    
    /**
     * 
     * запрос на файл записи разговора
     *
     * @param call_id – уникальный id звонка, этот id указан в названии файла с записью разговора (уникален для каждой записи в статистике);
     * @param life_time – (опциональный) время жизни ссылки в секундах (минимум - 180, максимум - 5184000, по-умолчанию - 1800).
     * @param Pbx_call_id – постоянный ID внешнего звонка в АТС (не меняется при прохождении сценариев, голосового меню, transfer и т.д., отображается в статистике и уведомлениях);
     *
     * !!! документация задармы чуть-чуть припиздёхивает возвращается не link а links (массив) 
     *
     */    
    static function mod_zadarma_api_record($call_id, $life_time = null, $pbx_call_id = null)
    {
        /*
        {
            "status":"success",
            "link": "https://api.zadarma.com/v1/pbx/record/download/NjM3M..NzM2Mg/1-1458313316.343456-100-2016-01-11-100155.mp3",
            "lifetime_till": "2016-01-01 23:56:22"
        }
        */
        return static::mod_zadarma_api('/v1/pbx/record/request/', [
            
            'call_id' => $call_id, //'1458832388.1585217'
            'lifetime' => $life_time, // 180
            //'pbx_call_id' => $pbx_call_id, //'in_c2b77d043ae465a1072d3f745e071032b9485461'

        ] + (isset($pbx_call_id) ? [ 'pbx_call_id' => $pbx_call_id ] : []));
        
    }    

}    
