<?php

namespace App\Tests\Api;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\HttpClient;

class RegistrationApiTest extends TestCase
{
    private $baseUrl = 'https://go.dev-01.ru/api/v1/account';

    // Проверка, что сервер вообще отвечает (пинг)
    public function testPingApiIsAlive()
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/register');
        $status = $response->getStatusCode();
        fwrite(STDERR, "\nPING RESPONSE STATUS: $status\n");
        $this->assertTrue(in_array($status, [404, 405, 400, 200, 201]), "Сервер недоступен, получен статус $status");
    }

    // Позитивный тест: регистрация с валидными данными
    public function testRegisterUserWithValidData()
    {
        $client = HttpClient::create();
        $postData = [
            'name' => 'Александр',
            'patronymic' => 'Иванович',
            'surname' => 'Иванов',
            'phone' => '+79991234567',
            'email' => 'aleksandr' . rand(10000, 99999) . '@mail.ru',
            'address' => 'г. Санкт-Петербург, ул. Ленина, д. 1',
            'refovod_code' => '334',
            'password' => 'VerySecret123'
        ];

        $response = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => $postData,
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        fwrite(STDERR, "\nVALID RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");

        $this->assertTrue($status === 200 || $status === 201, "Ожидался статус 200 или 201, получено $status");
        $data = json_decode($content, true);
        $this->assertArrayHasKey('login_link', $data, 'В ответе должен быть login_link');
    }

    // Негативный тест: неверный email
    public function testRegisterUserWithInvalidEmail()
    {
        $client = HttpClient::create();
        $postData = [
            'name' => 'Александр',
            'patronymic' => 'Иванович',
            'surname' => 'Иванов',
            'phone' => '+79991234567',
            'email' => 'wrongemail',
            'address' => 'г. Санкт-Петербург, ул. Ленина, д. 1',
            'refovod_code' => '334',
            'password' => 'VerySecret123'
        ];

        $response = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => $postData,
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        fwrite(STDERR, "\nINVALID EMAIL RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");

        $this->assertEquals(400, $status, 'Ожидался статус 400');
        $data = json_decode($content, true);
        $this->assertArrayHasKey('reason', $data, 'В ответе должна быть причина ошибки');
    }

    // Негативный тест: короткий пароль
    public function testRegisterUserWithShortPassword()
    {
        $client = HttpClient::create();
        $postData = [
            'name' => 'Александр',
            'patronymic' => 'Иванович',
            'surname' => 'Иванов',
            'phone' => '+79991234567',
            'email' => 'alex' . rand(10000, 99999) . '@mail.ru',
            'address' => 'г. Санкт-Петербург, ул. Ленина, д. 1',
            'refovod_code' => '334',
            'password' => '123'
        ];

        $response = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => $postData,
        ]);

        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        fwrite(STDERR, "\nSHORT PASSWORD RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");

        $this->assertEquals(400, $status, 'Ожидался статус 400');
    }

    // Нет авторизации
    public function testRegisterWithoutAuth()
    {
        $client = HttpClient::create();
        $postData = [
            'name' => 'Тест',
            'patronymic' => 'Тестович',
            'surname' => 'Тестов',
            'phone' => '+79991234567',
            'email' => 'noauth' . rand(10000,99999) . '@mail.ru',
            'address' => 'г. Тест',
            'refovod_code' => '334',
            'password' => 'Test12345'
        ];
        $response = $client->request('POST', $this->baseUrl . '/register', [
            'json' => $postData,
        ]);
        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        fwrite(STDERR, "\nNO AUTH RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");
        $this->assertTrue($status >= 400, 'Должна быть ошибка авторизации');
    }

    // Отсутствует обязательное поле (email)
    public function testRegisterWithoutEmail()
    {
        $client = HttpClient::create();
        $postData = [
            'name' => 'Александр',
            'patronymic' => 'Иванович',
            'surname' => 'Иванов',
            'phone' => '+79991234567',
            // 'email' => 'alex' . rand(10000,99999) . '@mail.ru', // Нет email!
            'address' => 'г. Санкт-Петербург, ул. Ленина, д. 1',
            'refovod_code' => '334',
            'password' => 'VerySecret123'
        ];
        $response = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => $postData,
        ]);
        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        fwrite(STDERR, "\nNO EMAIL RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");
        $this->assertEquals(400, $status, 'Ожидался статус 400 при отсутствии email');
    }

    // Повторная регистрация с тем же email
    public function testRegisterWithExistingEmail()
    {
        $client = HttpClient::create();
        $email = 'existing' . rand(10000, 99999) . '@mail.ru';
        // Сначала зарегистрировать пользователя
        $response1 = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => [
                'name' => 'Александр',
                'patronymic' => 'Иванович',
                'surname' => 'Иванов',
                'phone' => '+79991234567',
                'email' => $email,
                'address' => 'г. Санкт-Петербург, ул. Ленина, д. 1',
                'refovod_code' => '334',
                'password' => 'VerySecret123'
            ],
        ]);
        // Потом попытаться зарегистрировать с тем же email ещё раз
        $response2 = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => [
                'name' => 'Александр',
                'patronymic' => 'Иванович',
                'surname' => 'Иванов',
                'phone' => '+79991234567',
                'email' => $email,
                'address' => 'г. Санкт-Петербург, ул. Ленина, д. 1',
                'refovod_code' => '334',
                'password' => 'VerySecret123'
            ],
        ]);
        $status = $response2->getStatusCode();
        $content = $response2->getContent(false);
        fwrite(STDERR, "\nEXISTING EMAIL RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");
        $this->assertEquals(400, $status, 'Ожидался статус 400 при повторной регистрации');
    }

    // Слишком длинные значения
    public function testRegisterWithLongValues()
    {
        $client = HttpClient::create();
        $longString = str_repeat('A', 300);
        $response = $client->request('POST', $this->baseUrl . '/register', [
            'auth_basic' => ['realuser', 'truestrongpassword'],
            'json' => [
                'name' => $longString,
                'patronymic' => $longString,
                'surname' => $longString,
                'phone' => '+79991234567',
                'email' => 'long' . rand(10000,99999) . '@mail.ru',
                'address' => $longString,
                'refovod_code' => '334',
                'password' => 'VerySecret123'
            ],
        ]);
        $status = $response->getStatusCode();
        $content = $response->getContent(false);
        fwrite(STDERR, "\nLONG VALUES RESPONSE STATUS: $status\nRESPONSE BODY: $content\n");
        $this->assertTrue($status >= 400, 'Должна быть ошибка при слишком длинных значениях');
    }
}

