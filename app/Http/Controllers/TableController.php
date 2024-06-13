<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\TableLinkCollection;
use App\Models\TableLink;
use Illuminate\Http\Request;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Drive;

class TableController extends Controller
{

    private function createClient(){
        // Создаем клиент Google
        $client = new Google_Client();
        $client->setApplicationName(config('app.name'));
        $client->setScopes([Google_Service_Sheets::SPREADSHEETS, Google_Service_Drive::DRIVE]);
        $client->setAuthConfig(storage_path('app/credentials.json')); // путь к файлу учетных данных
        $client->setAccessType('offline');
        return $client;
    }

    private function createSpreadsheet($service,$title){
        $spreadsheet = new \Google_Service_Sheets_Spreadsheet([
            'properties' => [
                'title' => $title
            ]
        ]);

        $spreadsheet = $service->spreadsheets->create($spreadsheet, [
            'fields' => 'spreadsheetId'
        ]);

        return $spreadsheet->spreadsheetId;
    }

    public function store(StoreOrderRequest $request)
    {
        $order_data = $request->validated();
        
        // Создаем клиент Google
        $client = $this->createClient();
        // Создаем сервис Google Sheets
        $service = new Google_Service_Sheets($client);

        // Создаем новую таблицу
        $spreadsheet_id = $this->createSpreadsheet($service, "Заявка");
        
        // Заполняем таблицу данными
        $values = [
            ['Имя', 'Фамилия', 'Телефонный номер', 'Текст заявки'],
            [$order_data['firstName'], $order_data['lastName'], $order_data['phone'], $order_data['text']],
        ];

        $body = new \Google_Service_Sheets_ValueRange([
            'values' => $values
        ]);

        $service->spreadsheets_values->update(
            $spreadsheet_id,
            'A1',
            $body,
            ['valueInputOption' => 'RAW']
        );

        // Открываем доступ к таблице для всех, у кого есть ссылка
        $driveService = new Google_Service_Drive($client);
        $permission = new \Google_Service_Drive_Permission();
        $permission->setType('anyone');
        $permission->setRole('reader');
        $driveService->permissions->create($spreadsheet_id, $permission);

        $link = 'https://docs.google.com/spreadsheets/d/' . $spreadsheet_id;

        // Сохраняем ссылку в базу данных
        TableLink::create(['url' => $link]);

        return response()->json(["success" => true]);
    }

    public function index(){
        //возвращаем список ссылок
        return new TableLinkCollection(TableLink::all());
    }
}
