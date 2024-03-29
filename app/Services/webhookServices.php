<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;

class webhookServices
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function entity_edit(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        Log::info('Webhook Received:', $data);

        $subdomain = $data['account']['subdomain'];

        if (isset($data['leads']['update'])) {
            foreach ($data['leads']['update'] as $lead) {
                $leadId = $lead['id'];
                $newName = $lead['name'];
                $newPrice = $lead['price'];  // Предполагается, что цена передается в обновлениях
                $responsibleUserId = $lead['responsible_user_id'];
                $entityType = 'leads';
                $updateTime = date('Y-m-d H:i:s', $lead['updated_at']);

                $userName = $this->getUserName($subdomain, $responsibleUserId);
                $noteText = "Новое название сделки: $newName, Новый ответственный: $userName, Новая цена: $newPrice, Время изменения: $updateTime";

                $this->addNoteToEntity($subdomain, $entityType, $leadId, $noteText);
            }
        }

        if (isset($data['contacts']['update'])) {
            foreach ($data['contacts']['update'] as $contact) {
                $contactId = $contact['id'];
                $newName = $contact['name'];
                $responsibleUserId = $contact['responsible_user_id'];
                $newPhone = $this->extractPhoneNumber($contact['custom_fields']);
                $entityType = 'contacts';
                $updateTime = date('Y-m-d H:i:s', $contact['updated_at']);

                $userName = $this->getUserName($subdomain, $responsibleUserId);
                $noteText = "Новое название контакта: $newName, Новый ответственный: $userName, Новый телефон: $newPhone, Время изменения: $updateTime";

                $this->addNoteToEntity($subdomain, $entityType, $contactId, $noteText);
            }
        }

        return response()->json(['status' => 'success']);
    }


    private function extractPhoneNumber($customFields)
    {
        foreach ($customFields as $field) {
            if ($field['code'] === 'PHONE' && !empty($field['values'])) {
                return $field['values'][0]['value'];
            }
        }
        return 'Номер не указан';
    }

    public function entity_create(Request $request): \Illuminate\Http\JsonResponse
    {
        $data = $request->all();
        $subdomain = $data['account']['subdomain'];
        Log::info('Webhook Received:', $data);
        if (isset($data['leads'])) {
            foreach ($data['leads']['add'] as $lead) {
                $leadId = $lead['id'];
                $leadName = $lead['name'];
                $price = $lead['price'];
                $responsibleUserId = $lead['responsible_user_id'];
                $entityType = 'leads';

                $userName = $this->getUserName($subdomain, $responsibleUserId);
                $noteText = "Название сделки: $leadName, Цена: $price, Ответственный: $userName, Время добавления: " . date('Y-m-d H:i:s', $lead['date_create']);

                $this->addNoteToEntity($subdomain, $entityType, $leadId, $noteText);
            }
        }

        if (isset($data['contacts'])) {
            foreach ($data['contacts']['add'] as $contact) {
                $contactId = $contact['id'];
                $contactName = $contact['name'];
                $responsibleUserId = $contact['responsible_user_id'];
                $entityType = 'contacts';

                $userName = $this->getUserName($subdomain, $responsibleUserId);
                $noteText = "Название контакта: $contactName, Ответственный: $userName, Время добавления: " . date('Y-m-d H:i:s', $contact['date_create']);

                $this->addNoteToEntity($subdomain, $entityType, $contactId, $noteText);
            }
        }

        return response()->json(['status' => 'success']);
    }

    private function addNoteToEntity($subdomain, $entityType, $entityId, $noteText)
    {
        $url = "https://$subdomain.amocrm.ru/api/v4/$entityType/$entityId/notes";

        $noteData = [
            [
                'entity_id' => $entityId,
                'note_type' => 'common',
                'params' => [
                    'text' => $noteText
                ],
            ]
        ];

        try {
            $response = $this->client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('AMOCRM_ACCESS_TOKEN'),
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode($noteData),
                'verify' => false
            ]);

            Log::info("Note added to $entityType: " . $response->getBody());
        } catch (\Exception $e) {
            Log::error("Error adding note to $entityType: " . $e->getMessage());
        }
    }


    private function getUserName($subdomain, $userId)
    {
        $url = "https://$subdomain.amocrm.ru/api/v4/users";

        try {
            $response = $this->client->request('GET', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . env('AMOCRM_ACCESS_TOKEN')
                ],
                'verify' => false
            ]);

            $users = json_decode($response->getBody()->getContents(), true);

            foreach ($users['_embedded']['users'] as $user) {
                if ($user['id'] == $userId) {
                    return $user['name'];
                }
            }

        } catch (\Exception $e) {
            Log::error("Error fetching user: " . $e->getMessage());
            return null;
        }

        return null;
    }


}

