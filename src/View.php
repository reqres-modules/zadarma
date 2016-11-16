<?php
namespace Reqres\Module\Zadarma;

trait View {

    
    /**
     *
     * Переадрессовываем входящий звонок на $caller_name
     *
     * @param redirect – id сценария редиректа или внутренний номер АТС, "blacklist" - в этом случае звонок будет отклонен с сигналом занято;
	 * @param caller_name – можно дать имя входящему номеру.
     *
     */
    function mod_zadarma_inbound_redirect($redirect, $caller_name)
    {

        exit(json_encode([
            
            'redirect' => $redirect,
            'caller_name' => $caller_name
            
        ]));

    }

    /**
     *
     * Выводим сообщение об ошибке полученое от сервиса Zadarma
     *
     * Ошибка выводится по Reqres протоколу,
     * поэтому страница на которой происходит действие должна быть уведомлена о наличии протокола
     *
     * @param message Текст ошибки
     *
     */
    abstract function mod_zadarma_error($message);
    
}
