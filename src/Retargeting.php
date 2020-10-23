<?php

namespace DialogBoxStudio\VkCollectingRetargeting;


class Retargeting
{
    private string $path_base = __DIR__ . '/../resource/base.json';

    private array $error = [
        0 => 'Нет ошибок.',
        1 => 'Нельзя добавить самого себя.',
        2 => 'Сообщение относиться к сообществу.',
        3 => 'Пользователь уже есть в базе.',
        4 => 'Нет пересланного сообщения.'
    ];

    private int $user_id;

    private object $data;
    
    private object $vk;

    private string $comment;

    public function __construct(int $user_id, object $data, object $vk, string $comment = 'Без комментария')
    {
        $this->user_id = $user_id;
        $this->data = $data;
        $this->vk = $vk;
        $this->comment = $comment;
    }

    public function getAnswer(): array
    {
        $text = [];
        $params = [];
        if ($this->isForwardMessage() === true) {
            foreach ($this->getId() as $item => $value) {
                if (isset($value['error'])) {
                    $text[] =  "\xf0\x9f\x9a\xab".' Ошибка. '.$value['error'];
                } else {
                    $users_info = $this->getUsersInfo($value['id']);
                    $text_value = "\xe2\x9c\x85".' Пользователь с ID '.$value['id']. ' добавлен в базу ретаргетинга. '.PHP_EOL.PHP_EOL;
                    $text_value.= 'Имя: '.$users_info['first_name'].PHP_EOL;
                    $text_value.= 'Фамилия: '.$users_info['last_name'].PHP_EOL;
                    $text_value.= 'Пол: '.$users_info['sex'].PHP_EOL;
                    $text_value.= 'Дата рождения: '.$users_info['bdate'].PHP_EOL;
                    $text_value.= 'Страна: '.$users_info['country'].PHP_EOL;
                    $text_value.= 'Город: '.$users_info['city'].PHP_EOL;
                    $text_value.= 'Комментарий: '.$this->comment.PHP_EOL;
                    $text_value.= 'Дата добавления: '.date('d.m.Y').PHP_EOL.PHP_EOL;
                    $text[] =  $text_value;
                    $params[] =  [
                        [
                            $value['id'],
                            $users_info['first_name'],
                            $users_info['last_name'],
                            $users_info['sex'],
                            $users_info['bdate'],
                            $users_info['city'],
                            $users_info['country'],
                            $this->comment,
                            date('d.m.Y')
                            ]
                        ];
                }
            }
        }
        
        if ($this->isForwardMessage() === false) {
            $text.= $this->getError($this->user_id, 4);
        }
        
        return ['text' => $text, 'params' => $params, 'count' => $this->countBase()];
    }

    private function isForwardMessage(): bool
    {
        if (isset($this->data->object->message->fwd_messages) and count($this->data->object->message->fwd_messages) !== 0) {
            return true;
        } else {
            return false;
        }
    }

    public function getId(): array
    {
        $array_id = [];
        foreach ($this->data->object->message->fwd_messages as $item => $value) {
            $base = $this->loadingBase();
            if ($value->from_id !== $this->user_id and $value->from_id > 0 and in_array($value->from_id, $base) == false) {
                $array_id[] = ['id' => $value->from_id];
                $base[] = $value->from_id;
            } else {
                $array_id[] = ['id' => $value->from_id, 'error' => $this->getError($value->from_id)];
            }
            $this->saveBase($base);
        }
        return $array_id;
    }

    private function getError(int $from_id, int $num = 0)
    {
        $error = $this->error[0];
        if ($num == 0) {
            if ($from_id == $this->user_id) {
                $error = $this->error[1];
            }

            if ($from_id < 0) {
                $error = $this->error[2];
            }

            if (in_array($from_id, $this->loadingBase()) == true) {
                $error = $this->error[3];
            }
        } else {
            $error = $this->error[$num];
        }
        return $error;
    }

    private function loadingBase(): array
    {
        return json_decode(file_get_contents($this->path_base), true);
    }

    private function saveBase(array $base): void
    {
        file_put_contents($this->path_base, json_encode($base));
    }

    private function countBase(): int
    {
        return count($this->loadingBase());
    }

    private function getUsersInfo(int $from_id): array
    {
        $users_info = $this->vk->userInfo($from_id, ['sex', 'bdate', 'city', 'country']);
        return [
            'first_name' => $users_info['first_name'],
            'last_name' => $users_info['last_name'],
            'bdate' => (isset($users_info['bdate'])) ? $users_info['bdate'] : "не указана",
            'city' => (isset($users_info['city'])) ? $users_info['city']['title'] : "не указан",
            'country' => (isset($users_info['country'])) ? $users_info['country']['title'] : "не указана",
            'sex' => $this->getSexValue($users_info['sex'])
        ];
    }

    private function getSexValue(int $sex): string
    {
        switch ($sex) {
            case 0;
            default;
                $sex_value = "не указан";
                break;
            case 1;
                $sex_value = "женский";
                break;
            case 2;
                $sex_value = "мужской";
                break;
        }
        return $sex_value;
    }


}