<?php

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Models\TaskModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\LeadModel;

class AmoCrmController extends Controller
{
    private $apiClient;
    public function __construct()
    {
        $clientId = config('amocrm.client_id');
        $clientSecret = config('amocrm.client_secret');
        $redirectUri = config('amocrm.redirect_uri');
        $subdomain = config('amocrm.subdomain');
        $code = config('amocrm.code');
        $this->apiClient = new AmoCRMApiClient($clientId, $clientSecret, $redirectUri);
        $this->apiClient->setAccountBaseDomain($subdomain);
        if (!file_exists('token.json')) {
            $accessToken = $this->apiClient->getOAuthClient()->getAccessTokenByCode($code);
            $this->saveToken(
                [
                    'accessToken' => $accessToken->getToken(),
                    'refreshToken' => $accessToken->getRefreshToken(),
                    'expires' => $accessToken->getExpires(),
                    'baseDomain' => $this->apiClient->getAccountBaseDomain(),
                ]
            );
        } else {
            $accessToken = $this->getToken();
        }
        $this->apiClient->setAccessToken($accessToken)
            ->setAccountBaseDomain('test1screxy.amocrm.ru')
            ->onAccessTokenRefresh(
                function (\League\OAuth2\Client\Token\AccessTokenInterface $accessToken, string $baseDomain) {
                    $this->saveToken(
                        [
                            'accessToken' => $accessToken->getToken(),
                            'refreshToken' => $accessToken->getRefreshToken(),
                            'expires' => $accessToken->getExpires(),
                            'baseDomain' => $baseDomain,
                        ]
                    );
                }
            );
    }
    public function index()
    {
        return view("amo.main");
    }
    public function store(Request $request)
    {
        // $request->validate([
        //     'first_name' => 'required|string',
        //     'last_name' => 'required|string',
        //     'age' => 'required|numeric',
        //     'gender' => 'required|string',
        //     'phone' => 'required|numeric|digits:11',
        //     'email' => 'required|email'
        // ]);
        $first_name = $request->input('first_name');
        $last_name = $request->input('last_name');
        $age = $request->input('age');
        $gender = $request->input('gender');
        $phone = $request->input('phone');
        $email = $request->input('email');

        $apiClient = $this->apiClient;
        $contactService = $apiClient->contacts();
        $leadsService = $apiClient->leads();

        // $contact = new ContactModel();
        // //установить кастомные поля как у других контактов
        // $contact->setCustomFieldsValues($contactService->get()->last()->getCustomFieldsValues());

        // $this->setContactName($contact, $first_name, $last_name);
        // $this->setContactAge($contact, $age);
        // $this->setContactGender($contact, $gender);
        // $this->setContactNumber($contact, $phone);
        // $this->setContactEmail($contact, $email);
        // $apiClient->contacts()->addOne($contact);
        $contact = $contactService->getOne('11259657');
        $lead = new LeadModel();
        $lead->setName('Тестовая сделка из Api2')
            ->setPrice(999)
            ->setContacts(
                (new ContactsCollection())
                    ->add(
                        $contact
                    )
            )->setResponsibleUserId($this->getRandomUserId());
        $leadsService->addOne($lead);

        $tasksService = $apiClient->tasks();
        $task = new TaskModel();
        $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
            ->setText('Новая задач из Api')
            ->setCompleteTill(mktime(10, 0, 0, 10, 3, 2024))
            ->setEntityType(EntityTypesInterface::LEADS)
            ->setEntityId($lead->getId())
            ->setDuration(30 * 60 * 60) //30 минут
            ->setResponsibleUserId($lead->getResponsibleUserId());
        $tasksService->addOne($task);

        return response()->json([
            'message' => 'ok',
        ], 201);
    }
    private function saveToken(array $accessToken)
    { {
            if (
                isset($accessToken)
                && isset($accessToken['accessToken'])
                && isset($accessToken['refreshToken'])
                && isset($accessToken['expires'])
                && isset($accessToken['baseDomain'])
            ) {
                $data = [
                    'accessToken' => $accessToken['accessToken'],
                    'expires' => $accessToken['expires'],
                    'refreshToken' => $accessToken['refreshToken'],
                    'baseDomain' => $accessToken['baseDomain'],
                ];

                file_put_contents('token.json', json_encode($data));
            } else {
                exit('Invalid access token ' . var_export($accessToken, true));
            }
        }
    }
    private function getToken()
    {
        if (!file_exists('token.json')) {
            exit('Access token file not found');
        }

        $accessToken = json_decode(file_get_contents('token.json'), true);

        if (
            isset($accessToken)
            && isset($accessToken['accessToken'])
            && isset($accessToken['refreshToken'])
            && isset($accessToken['expires'])
            && isset($accessToken['baseDomain'])
        ) {
            return new AccessToken([
                'access_token' => $accessToken['accessToken'],
                'refresh_token' => $accessToken['refreshToken'],
                'expires' => $accessToken['expires'],
                'baseDomain' => $accessToken['baseDomain'],
            ]);
        } else {
            exit('Invalid access token ' . var_export($accessToken, true));
        }
    }
    private function setContactNumber(ContactModel $contact, int $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле телефон
        $contactCustomFields->getBy('fieldCode', 'PHONE')->setValues(
            (new MultitextCustomFieldValueCollection())->add(
                (new MultitextCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactEmail(ContactModel $contact, string $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        $contactCustomFields->getBy('fieldCode', 'EMAIL')->setValues(
            (new MultitextCustomFieldValueCollection())->add(
                (new MultitextCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactName(ContactModel $contact, string $first_name, string $last_name)
    {
        $contact->setFirstName($first_name)->setLastName($last_name);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactAge(ContactModel $contact, int $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        $contactCustomFields->getBy('fieldName', 'Возраст')->setValues(
            (new NumericCustomFieldValueCollection())->add(
                (new NumericCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function setContactGender(ContactModel $contact, string $value)
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        $contactCustomFields->getBy('fieldName', 'Пол')->setValues(
            (new SelectCustomFieldValueCollection())->add(
                (new SelectCustomFieldValueModel())->setValue($value)
            )
        );
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
        // $this->apiClient->contacts()->updateOne($contact);
    }
    private function getRandomUserId()
    {
        $apiClient = $this->apiClient;
        $usersService = $apiClient->users();
        $users = $usersService->get()->toArray();
        $index = random_int(0, count($users) - 1);
        return $users[$index]['id'];
    }
}
