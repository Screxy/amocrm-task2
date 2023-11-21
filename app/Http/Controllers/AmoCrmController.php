<?php
declare(strict_types=1);

namespace App\Http\Controllers;

use AmoCRM\Client\AmoCRMApiClient;
use AmoCRM\Collections\CatalogElementsCollection;
use AmoCRM\Collections\CustomFieldsValuesCollection;
use AmoCRM\Models\CustomFieldsValues\NumericCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\SelectCustomFieldValuesModel;
use AmoCRM\Models\NoteType\CommonNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use AmoCRM\Helpers\EntityTypesInterface;
use AmoCRM\Models\CustomFieldsValues\ValueModels\SelectCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\SelectCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\MultitextCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\MultitextCustomFieldValuesModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\MultitextCustomFieldValueCollection;
use AmoCRM\Models\CustomFieldsValues\ValueModels\NumericCustomFieldValueModel;
use AmoCRM\Models\CustomFieldsValues\ValueCollections\NumericCustomFieldValueCollection;
use League\OAuth2\Client\Token\AccessToken;
use AmoCRM\Collections\ContactsCollection;
use AmoCRM\Models\TaskModel;
use AmoCRM\Models\ContactModel;
use AmoCRM\Models\LeadModel;
use AmoCRM\Collections\LinksCollection;
use AmoCRM\Models\CatalogElementModel;
use AmoCRM\Models\Customers\CustomerModel;
use \Illuminate\Contracts\View\View;


class AmoCrmController extends Controller
{
    const STATUS_CODE_CREATED = 201;

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

    public function index(): View
    {
        return view("amo.main");
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'first_name' => 'required|string',
            'last_name' => 'required|string',
            'age' => 'required|numeric',
            'gender' => 'required|string',
            'phone' => 'required|numeric|digits:10',
            'email' => 'required|email',
        ]);
        $firstName = $request->input('first_name');
        $lastName = $request->input('last_name');
        $age = $request->input('age');
        $gender = $request->input('gender');
        $phone = $request->input('phone');
        $email = $request->input('email');

        $apiClient = $this->apiClient;
        $contactService = $apiClient->contacts();
        $leadsService = $apiClient->leads();
        $customersService = $apiClient->customers();
        //Проверка на дубль
        $checkContact = $contactService->get(null, ['leads']);
        $isContactDuplicate = false;
        foreach ($checkContact as $contactItem) {
            //достать поле телефон
            $contactPhone = $contactItem->getCustomFieldsValues()->getBy('fieldCode', 'PHONE')->getValues()->all()[0]->getValue();
            $isContactDuplicate = $contactPhone === $phone;
            if ($isContactDuplicate) {
                $contact = $contactItem;
                break;
            }
        }
        //Если контакт имеет дубль
        if ($isContactDuplicate) {
            //проверка на успешный статус сделок контакта
            $contactLeads = $contact->getLeads();
            $isHaveCompletedLeads = false;
            if (!empty($contactLeads)) {
                foreach ($contactLeads as $lead) {
                    $syncedLead = $leadsService->syncOne($lead);
                    $isHaveCompletedLeads = $syncedLead->getStatusId() === 142;
                    if ($isHaveCompletedLeads) {
                        break;
                    }
                }
            }
            if ($isHaveCompletedLeads) {
                //создание покупателя
                $customer = new CustomerModel();
                $customer->setName('Покупатель из Api')->setNextDate(time() + (4 * 24 * 60 * 60));
                $links = new LinksCollection();
                $links->add($contact);
                $customer = $customersService->addOne($customer);
                $customersService->link($customer, $links);
            } else {
                //создание примечания
                $contactNotesService = $apiClient->notes(EntityTypesInterface::CONTACTS);
                $commonNote = new CommonNote();
                $commonNote->setText('Была попытка создать дубль контакта')->setEntityId($contact->getId());
                $contactNotesService->addOne($commonNote);
            }
        } else {
            //если дубля нет, создаем новый контакт, сделку, задачу
            $contact = new ContactModel();
            $this->setContactName($contact, $firstName, $lastName);
            $this->setContactAge($contact, $age);
            $this->setContactGender($contact, $gender);
            $this->setContactPhone($contact, $phone);
            $this->setContactEmail($contact, $email);
            $apiClient->contacts()->addOne($contact);

            // Создаем сделку
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

            // создаем задачу
            $tasksService = $apiClient->tasks();
            $task = new TaskModel();
            $task->setTaskTypeId(TaskModel::TASK_TYPE_ID_FOLLOW_UP)
                ->setText('Новая задач из Api')
                ->setCompleteTill($this->getNextWorkingDayTime())
                ->setEntityType(EntityTypesInterface::LEADS)
                ->setEntityId($lead->getId())
                ->setDuration(60 * 60 * 9)
                ->setResponsibleUserId($lead->getResponsibleUserId());
            $tasksService->addOne($task);

            $catalogsService = $apiClient->catalogs();
            $catalogsCollection = $catalogsService->get();
            $catalog = $catalogsCollection->getBy('catalogType', 'products');

            //сервис элементов товаров
            $catalogElementsService = $apiClient->catalogElements($catalog->getId());

            // создаем и добавляем товары
            $catalogElementCollection = new CatalogElementsCollection();
            $catalogElement = new CatalogElementModel();
            $catalogElement->setName('Новый товар из Api');
            $catalogElement2 = new CatalogElementModel();
            $catalogElement2->setName('Новый товар из Api 2');
            $catalogElementCollection->add($catalogElement)->add($catalogElement2);
            $catalogElementsService->add($catalogElementCollection);

            //привязываем товары к сделке
            $links = new LinksCollection();
            $links->add($catalogElement);
            $links->add($catalogElement2);
            $leadsService->link($lead, $links);

        }
        return response()->json([
            'message' => 'ok',
        ], self::STATUS_CODE_CREATED);
    }

    private function saveToken(array $accessToken): void
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
    private function getToken(): AccessToken
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

    private function setContactPhone(ContactModel $contact, string $value): ContactModel
    {

        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле телефон
        if (!empty($contactCustomFields)) {
            $contactCustomFields->add(
                (new NumericCustomFieldValuesModel())->setFieldId(1240825)->setFieldName('Телефон')->setValues(
                    (new MultitextCustomFieldValueCollection())->add(
                        (new MultitextCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        } else {
            $contactCustomFields = (new CustomFieldsValuesCollection())->add(
                (new NumericCustomFieldValuesModel())->setFieldId(1240825)->setFieldName('Телефон')->setValues(
                    (new MultitextCustomFieldValueCollection())->add(
                        (new MultitextCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        }
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
    }

    private function setContactEmail(ContactModel $contact, string $value): ContactModel
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле email
        if (!empty($contactCustomFields)) {
            $contactCustomFields->add(
                (new MultitextCustomFieldValuesModel())->setFieldId(1240827)->setFieldName('Email')->setValues(
                    (new MultitextCustomFieldValueCollection())->add(
                        (new MultitextCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        } else {
            $contactCustomFields = (new CustomFieldsValuesCollection())->add(
                (new MultitextCustomFieldValuesModel())->setFieldId(1240827)->setFieldName('Email')->setValues(
                    (new MultitextCustomFieldValueCollection())->add(
                        (new MultitextCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        }
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
    }

    private function setContactName(ContactModel $contact, string $firstName, string $lastName): ContactModel
    {
        $contact->setFirstName($firstName)->setLastName($lastName);
        return $contact;
    }

    private function setContactAge(ContactModel $contact, string $value): ContactModel
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле age
        if (!empty($contactCustomFields)) {
            $contactCustomFields->add(
                (new NumericCustomFieldValuesModel())->setFieldId(1345287)->setFieldName('Возраст')->setValues(
                    (new NumericCustomFieldValueCollection())->add(
                        (new NumericCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        } else {
            $contactCustomFields = (new CustomFieldsValuesCollection())->add(
                (new NumericCustomFieldValuesModel())->setFieldId(1345287)->setFieldName('Возраст')->setValues(
                    (new NumericCustomFieldValueCollection())->add(
                        (new NumericCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        }
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
    }

    private function setContactGender(ContactModel $contact, string $value): ContactModel
    {
        $contactCustomFields = $contact->getCustomFieldsValues();
        //изменить поле пол
        if (!empty($contactCustomFields)) {
            $contactCustomFields->add(
                (new SelectCustomFieldValuesModel())->setFieldId(1345279)->setFieldName('Пол')->setValues(
                    (new SelectCustomFieldValueCollection())->add(
                        (new SelectCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        } else {
            $contactCustomFields = (new CustomFieldsValuesCollection())->add(
                (new SelectCustomFieldValuesModel())->setFieldId(1345279)->setFieldName('Пол')->setValues(
                    (new SelectCustomFieldValueCollection())->add(
                        (new SelectCustomFieldValueModel())->setValue($value)
                    )
                )
            );
        }
        $contact->setCustomFieldsValues($contactCustomFields);
        return $contact;
    }

    private function getRandomUserId()
    {
        $apiClient = $this->apiClient;
        $usersService = $apiClient->users();
        $users = $usersService->get()->toArray();
        $index = random_int(0, count($users) - 1);
        return $users[$index]['id'];
    }

    private function getNextWorkingDayTime()
    {
        date_default_timezone_set('Europe/Moscow');

        $currentTimestamp = time();
        $fourDaysLaterTimestamp = strtotime('+4 days', $currentTimestamp);
        while (date('N', $fourDaysLaterTimestamp) >= 6) {
            $fourDaysLaterTimestamp = strtotime('+1 day', $fourDaysLaterTimestamp);
        }
        $workingHoursStart = strtotime('09:00', $fourDaysLaterTimestamp);
        return $workingHoursStart;
    }
}
