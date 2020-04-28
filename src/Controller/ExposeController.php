<?php

namespace Alchemy\ExposePlugin\Controller;

use Alchemy\ExposePlugin\Configuration\Config;
use Alchemy\ExposePlugin\Provider\ControllerServiceProvider;
use Alchemy\Phrasea\Application as PhraseaApplication;
use Alchemy\Phrasea\Controller\Controller;
use Alchemy\Phrasea\Controller\RecordsRequest;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\Request;

class ExposeController extends Controller
{
    public function exposeAction(PhraseaApplication $app, Request $request)
    {
        $config = $app[ControllerServiceProvider::PLUGIN_NAMESPACE . '.config'];
        $config = $config['expose_plugin'];
        if (!isset($config['token'])) {
            $config = $this->generateAndSaveToken($config);
        }

        return $app->json([
            'content' => $app['twig']->render(
                $app['expose_plugin.name'].'/prod/expose.html.twig', [
                    'lst'       => $request->get('lst'),
                    'config'    => $config
                ]
            )
        ]);
    }

    public function listPublicationAction(PhraseaApplication $app, Request $request)
    {
        $config = $app[ControllerServiceProvider::PLUGIN_NAMESPACE . '.config'];
        $config = $config['expose_plugin'];
        $exposeClient = new Client(['base_uri' => $config['exposeBaseUri'], 'http_errors' => false]);

        if (!isset($config['token'])) {
            $config = $this->generateAndSaveToken($config);
        }

        $response = $exposeClient->get('/publications', [
            'headers' => [
                'Authorization' => 'Bearer '. $config['token'],
                'Content-Type'  => 'application/json'
            ]
        ]);

        $publications = [];
        if ($response->getStatusCode() == 200) {
            $body = json_decode($response->getBody()->getContents(),true);
            $publications = $body['hydra:member'];
        }
        return $app['twig']->render('phraseanet-plugin-expose/prod/expose_list_publication.html.twig', [
            'publications' => $publications,
            'exposeFrontUri' => \p4string::addEndSlash($config['exposeFrontUri'])
        ]);
    }

    public function createPublicationAction(PhraseaApplication $app, Request $request)
    {
        $records = RecordsRequest::fromRequest($app, $request);
        $config = $app[ControllerServiceProvider::PLUGIN_NAMESPACE . '.config'];
        $config = $config['expose_plugin'];

        $exposeClient = new Client(['base_uri' => $config['exposeBaseUri'], 'http_errors' => false]);

        try {
            if (!isset($config['token'])) {
                $config = $this->generateAndSaveToken($config);
            }

            $response = $this->postPublication($exposeClient, $config['token'], json_decode($request->get('publicationData'), true));

            if ($response->getStatusCode() == 401) {
                $config = $this->generateAndSaveToken($config);

                $response = $this->postPublication($exposeClient, $config['token'], json_decode($request->get('publicationData'), true));
            }

            if ($response->getStatusCode() !==201) {
                return $app->json([
                    'success' => false,
                    'message' => "An error occurred when creating publication: status-code " . $response->getStatusCode()
                ]);
            }

            $publicationsResponse = json_decode($response->getBody(),true);
        } catch (\Exception $e) {
            return $app->json([
                'success' => false,
                'message' => "An error occurred when creating publication!"
            ]);
        }

        /** @var \record_adapter $record */
        foreach ($records as $record) {
            try {
                $response = $exposeClient->post('/assets', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $config['token']
                    ],
                    'multipart' => [
                        [
                            'name'      => 'file',
                            'contents'  => fopen($record->get_subdef('document')->getRealPath(), 'r')
                        ],
                        [
                            'name'      => 'publication_id',
                            'contents'  => $publicationsResponse['id'],

                        ],
                        [
                            'name'      => 'slug',
                            'contents'  => 'asset_'. $record->getId()
                        ]

                    ]
                ]);

                if ($response->getStatusCode() !==201) {
                    return $app->json([
                        'success' => false,
                        'message' => "An error occurred when creating asset: status-code " . $response->getStatusCode()
                    ]);
                }
                $assetsResponse = json_decode($response->getBody(),true);

                // add preview sub-definition

                $this->postSubDefinition(
                    $exposeClient,
                    $config['token'],
                    $record->get_subdef('preview')->getRealPath(),
                    $assetsResponse['id'],
                    'preview',
                    true
                );

                // add thumbnail sub-definition

                $this->postSubDefinition(
                    $exposeClient,
                    $config['token'],
                    $record->get_subdef('thumbnail')->getRealPath(),
                    $assetsResponse['id'],
                    'thumbnail',
                    false,
                    true
                );


            } catch (\Exception $e) {
                return $app->json([
                    'success' => false,
                    'message' => "An error occurred when creating asset!"
                ]);
            }
        }

        $path = empty($publicationsResponse['slug']) ? $publicationsResponse['id'] : $publicationsResponse['slug'] ;
        $url = \p4string::addEndSlash($config['exposeFrontUri']) . $path;

        $link = "<a style='color:blue;' target='_blank' href='" . $url . "'>" . $url . "</a>";

        return $app->json([
            'success' => true,
            'message' => "Publication successfully created!",
            'link'    => $link
        ]);
    }

    private function postPublication(Client $exposeClient, $token, $publicationData)
    {
        return $exposeClient->post('/publications', [
            'headers' => [
                'Authorization' => 'Bearer '. $token,
                'Content-Type'  => 'application/json'
            ],
            'json' => $publicationData
        ]);
    }

    private function postSubDefinition(Client $exposeClient, $token, $path, $assetId, $subdefName, $isPreview = false, $isThumbnail = false)
    {
        return $exposeClient->post('/sub-definitions', [
            'headers' => [
                'Authorization' => 'Bearer ' .$token
            ],
            'multipart' => [
                [
                    'name'      => 'file',
                    'contents'  => fopen($path, 'r')
                ],
                [
                    'name'      => 'asset_id',
                    'contents'  => $assetId,

                ],
                [
                    'name'      => 'name',
                    'contents'  => $subdefName
                ],
                [
                    'name'      => 'use_as_preview',
                    'contents'  => $isPreview
                ],
                [
                    'name'      => 'use_as_thumbnail',
                    'contents'  => $isThumbnail
                ]
            ]
        ]);
    }

    /**
     * @param $config
     * @return mixed
     */
    private function generateAndSaveToken($config)
    {
        $oauthClient = new Client();
        $tokenBody = $oauthClient->post($config['exposeBaseUri'] . '/oauth/v2/token', [
            'json' => [
                'client_id'     => $config['clientId'],
                'client_secret' => $config['clientSecret'],
                'grant_type'    => 'client_credentials',
                'scope'         => 'publish'
            ]
        ])->getBody()->getContents();

        $tokenBody = json_decode($tokenBody,true);

        $config['token'] = $tokenBody['access_token'];

        Config::setConfiguration($config);
        $config = Config::getConfiguration();

        return $config['expose_plugin'];
    }
}
