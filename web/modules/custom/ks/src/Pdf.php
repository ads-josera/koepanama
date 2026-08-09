<?php

namespace Drupal\ks;

use Drupal;
use Drupal\Component\Render\FormattableMarkup;
use Google_Client;

/**
 * Defines a support class
 *
 * @ingroup ks
 */
class Pdf
{
    public static function getAvailableWebforms()
    {
        return ['contrato', 'chequeo', 'bienvenida', 'facturacion', 'entrega'];
    }

    public static function hasPdf($webform_id)
    {
        $webforms = static::getAvailableWebforms();
        return in_array($webform_id, $webforms);
    }

    public static function listar($webform_id)
    {
        $connection = Drupal::database();
        $result = $connection->query('SELECT * FROM {ks_pdf} WHERE webform_id=:webform_id', [':webform_id' => $webform_id]);
        return $result->fetchAllAssoc('pid', \PDO::FETCH_ASSOC);
    }

    public static function actualizar($webform_id, $campos, $pid = null)
    {
        $connection = Drupal::database();

        $fields = ['webform_id' => $webform_id, 'campos' => serialize($campos)];

        if ($pid) {
            $connection->update('ks_pdf')->fields($fields)->condition('pid', $pid)->execute();
        } else {
            $connection->insert('ks_pdf')->fields($fields)->execute();
        }
    }

    //Route
    public static function getFileRoute($form_id, $folio_str, $path = false, $preventCache = true)
    {
        $folio_prefijo = '';
        if ($form_id == 'contrato') {
            $folio_prefijo = 'ON-';
        }

        $folio_label = $folio_prefijo.$folio_str;
        $filename = strtoupper($form_id).'_'.$folio_label.'.pdf';

        $previewsLocation = 'public://pdf/previews/';
        $preview = $previewsLocation.$filename;

        return $path ? Drupal::service('file_system')->realpath($preview) : Drupal::service('file_url_generator')->generateAbsoluteString($preview) . ($preventCache ? '?v='.time() : '');
    }

    //Google Drive
    private static function googleCredential(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            throw new \RuntimeException(sprintf('Missing required environment variable %s.', $name));
        }

        return $value;
    }

    public static function getConfig()
    {
        $config = [
            'callback' => 'https://contratokoepa.com/oauth/callback',
            'keys'     => [
                            'id' => static::googleCredential('KS_GOOGLE_CLIENT_ID'),
                            'secret' => static::googleCredential('KS_GOOGLE_CLIENT_SECRET')
                        ],
            'scope'    => 'https://www.googleapis.com/auth/drive',
            'authorize_url_parameters' => [
                    'prompt' => 'consent',// to pass only when you need to acquire a new refresh token.
                    'access_type' => 'offline'
            ]
        ];

        return $config;
    }

    public static function getAdapter()
    {
        $config = static::getConfig();
        $adapter = new \Hybridauth\Provider\Google($config);
        return $adapter;
    }

    public static function getAccessToken()
    {
        $connection = Drupal::database();
        $result = $connection->query('SELECT provider_value FROM {ks_google_oauth} LIMIT 1');
        $result = $result->fetchAssoc();
        return json_decode($result['provider_value']);
    }

    public static function getRefreshToken()
    {
        $result = static::getAccessToken();
        return $result->refresh_token;
    }

    public static function updateAccessToken($token)
    {
        $connection = Drupal::database();
        $affected_rows = $connection->update('ks_google_oauth')->fields(['provider_value' => $token])->condition('pid', 1)->execute();
    }

    public static function refreshAccessToken()
    {
        $refresh_token = static::getRefreshToken();
        $client = new \GuzzleHttp\Client(['base_uri' => 'https://accounts.google.com']);
        $response = $client->request('POST', '/o/oauth2/token', [
              'form_params' => [
                  'grant_type' => 'refresh_token',
                  'refresh_token' => $refresh_token,
                  'client_id' => static::googleCredential('KS_GOOGLE_CLIENT_ID'),
                  'client_secret' => static::googleCredential('KS_GOOGLE_CLIENT_SECRET'),
              ],
          ]);
        $data = (array) json_decode($response->getBody());
        $data['refresh_token'] = $refresh_token;

        static::updateAccessToken(json_encode($data));
    }

    private const VENTA_ONLINE_FOLDER_ID = '1YoM3Ma6nvPp14agAldLRtrrY_TKM4WyE';//Folder ID

    public static function getService()
    {
        $client = new Google_Client();
        $proxy_token = (array) static::getAccessToken();
        $accessToken =[
              'access_token' => $proxy_token['access_token'],
              'expires_in' => $proxy_token['expires_in'],
          ];
        $client->setAccessToken($accessToken);
        $service = new \Google\Service\Drive($client);
        return $service;
    }

    public static function getFile($id)
    {
        $service = static::getService();

        try {
            return $service->files->get($id, ['fields' => 'webViewLink']);
        } catch (\Exception $e) {
            if (401 == $e->getCode()) {
                static::refreshAccessToken();
                return static::getFile($id);
            } else {
                Drupal::logger('ks')->warning($e->getMessage());
            }
        }
    }

    public static function getFolder($name, $parent = null)
    {
        $service = static::getService();

        try {
            $q = "name='".$name."' and mimeType='application/vnd.google-apps.folder'";

            if (!is_null($parent)) {
                $q .= " and '".$parent."' in parents";
            }

            $result = $service->files->listFiles(['q' => $q]);

            $files = $result->files;
            return empty($files) ? null : reset($files);
        } catch (\Exception $e) {
            Drupal::logger('ks')->error($e->getMessage());
            if (401 == $e->getCode()) {
                static::refreshAccessToken();
                return static::getFolder($name, $parent);
            } else {
                Drupal::logger('ks')->warning($e->getMessage());
            }
        }
    }

    public static function createFolder($name, $parent)
    {
        $service = static::getService();
        try {
            $postBody = new \Google\Service\Drive\DriveFile(['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder', 'parents' => [$parent]]);
            $result = $service->files->create($postBody);
            return $result;
        } catch (\Exception $e) {
            if (401 == $e->getCode()) {
                static::refreshAccessToken();
                return static::createFolder($name, $parent);
            } else {
                Drupal::logger('ks')->warning($e->getMessage());
            }
        }
        return null;
    }

    public static function uploadFile($file, $parent, $matricula = null)
    {
        $service = static::getService();
        try {
            $name = is_null($matricula) ? basename($file) : basename($file, '.pdf').'_'.$matricula.'.pdf';
            $type = mime_content_type($file);
            $postBody = new \Google\Service\Drive\DriveFile(['name' => $name, 'mimeType' => $type, 'parents' => [$parent]]);
            $result = $service->files->create($postBody, ['data' => file_get_contents($file), 'mimeType' => $type, 'uploadType' => 'multipart', 'supportsAllDrives' => true, 'includePermissionsForView' => 'published']);
            if ($result) {
                $result = static::getFile($result->id);//Reload to get webViewLink
            }

            return $result;
        } catch (\Exception $e) {
            if (401 == $e->getCode()) {
                static::refreshAccessToken();
                return static::uploadFile($file, $parent);
            } else {
                Drupal::logger('ks')->warning($e->getMessage());
            }
        }
        return null;
    }

    public static function processFile($file, $webform_id, $matricula = null, $year = null)
    {
        $year = is_null($year) ? date('Y') : $year;

        $folder = null;
        $path = [$year, $webform_id];
        foreach ($path as $folder_name) {
            $parent = is_null($folder) ? static::VENTA_ONLINE_FOLDER_ID : $folder->id;
            $folder = static::getFolder($folder_name, $parent);
            if (is_null($folder)) {
                $folder = static::createFolder($folder_name, $parent);
                if (is_null($folder)) {
                    return null;
                }
            }
        }

        return static::uploadFile($file, $folder->id, $matricula);
    }
}
