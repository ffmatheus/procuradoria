<?php

namespace App\Services;

use App\Data\Repositories\Users;
use App\Data\Repositories\Users as UsersRepository;
use App\Services\Traits\RemoteRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
class Authentication
{
    const LOGIN_URL = 'https://apiportal.alerj.rj.gov.br/api/v1.0/ldap/0IYFFiMHuUr1sYo6wEtjUsJQ7Zicg33SMuvtrFk9yEgwrORmblNSMdpTH0ZTRKX2BhADIusjXHInHW3cspyosOoNrbd5jObK5Uoh/login';

    const USER_INFO_URL = 'https://apiportal.alerj.rj.gov.br/api/v1.0/ldap/d6fFGg5h4jui1k5loFG3p7d6fg5h4j3kDS8HJ/user';

    const PERMISSIONS_URL = 'https://apiportal.alerj.rj.gov.br/api/v1.0/adm-user/K7k8H95loFpTH0ZTRKX2BhADIusjXHInHW3cspyosOoNrbd5jOG3pd61F4d6fg584Gg5h4DSjui1k/permissions';

    /**
     * @var Guzzle
     */
    protected $guzzle;

    protected $remoteRequest;

    /**
     * @var Users
     */
    protected $usersRepository;

    public function __construct(
        Users $usersRepository,
        RemoteRequest $remoteRequest
    ) {
        $this->usersRepository = $usersRepository;

        $this->remoteRequest = $remoteRequest;
    }

    public function normalizeCredentialsCase($request)
    {
        return $request->merge(['email' => Str::lower($request->get('email'))]);
    }

    public function attempt($request, $remember)
    {
        $request = $this->normalizeCredentialsCase($request);
        return $this->loginUser(
            $request,
            $this->loginRequest($request),
            $remember
        );
    }

    protected function extractUsernameFromEmail($email)
    {
        if (($pos = strpos($email, '@')) === false) {
            return $email;
        }

        return substr($email, 0, $pos);
    }

    protected function instantiateGuzzle()
    {
        $this->guzzle = new Guzzle();
    }

    /**
     * @param $request
     *
     * @return mixed
     */
    protected function loginRequest($request)
    {
        if (config('auth.authentication.mock')) {
            return $this->mockedAuthentication($request);
        }

        try {
            $response = $this->remoteRequest->post(
                static::LOGIN_URL,
                extract_credentials($request)
            );

            return $response;
        } catch (\Exception $exception) {
            //Timeout no login
            $usersRepository = app(UsersRepository::class);
            $user = $usersRepository->findByColumn(
                'username',
                extract_credentials($request)['username']
            );
            Log::info(
                'Exception na request de login do usuário ' .
                    extract_credentials($request)['username']
            );

            Log::info($exception);
            if (is_null($user)) {
                //Sistema de login fora do ar e usuário novo
                Log::error(
                    'O usuário ' .
                        extract_credentials($request)['username'] .
                        ' tentou fazer login, mas não foi possível pois o SGUS está fora do ar e não há histórico do usuário no banco de dados'
                );
                abort(403);
            } else {
                //Usuário já cadastrado
                if (
                    \Hash::check(
                        extract_credentials($request)['password'],
                        $user->password
                    )
                ) {
                    //Credenciais de login conferem com as salvas no banco
                    return $this->mockedAuthentication($request);
                } else {
                    //Credenciais de login não conferem com as salvas no banco
                    Log::info(
                        'O usuário ' .
                            extract_credentials($request)['username'] .
                            ' tentou fazer login, mas não foi possível pois o SGUS está fora do ar e a senha não confere com a senha do banco de dados'
                    );
                    return $this->failedAuthentication();
                }
            }
        }
    }

    private function logLoginError($username, $response)
    {
        Log::info('Falha no login do usuário ' . $username);
        Log::info($response);
    }

    /**
     * @param $request
     * @param $response
     * @param $remember
     *
     * @throws \Exception
     *
     * @return mixed
     */
    protected function loginUser($request, $response, $remember)
    {
        if ($success = $response['success']) {
            $success = $this->usersRepository->loginUser($request, $remember);

            if (!$success) {
                $this->logLoginError(
                    extract_credentials($request)['username'],
                    $response
                );
                return false;
            }

            $permissions = app(Authorization::class)->getUserPermissions(
                extract_credentials($request)['username']
            );

            $this->usersRepository->updateCurrentUserTypeViaPermissions(
                $permissions
            );
        } else {
            $this->logLoginError(
                extract_credentials($request)['username'],
                $response
            );
        }

        return $success;
    }

    /**
     * @param $credentials
     *
     * @return array
     */
    protected function mockedAuthentication($credentials)
    {
        return [
            'success' => true,
            'code' => 200,
            'message' => null,
            'data' => [
                'name' => [$credentials['username']],
                'email' => [$credentials['username'] . '@alerj.rj.gov.br'],
                'memberof' => [
                    'CN=ProjEsp,OU=SDGI,OU=Departamentos,OU=ALERJ,DC=alerj,DC=gov,DC=br',
                    'CN=SDGI,OU=SDGI,OU=Departamentos,OU=ALERJ,DC=alerj,DC=gov,DC=br'
                ],
                'description' => ['matricula: N/C']
            ]
        ];
    }

    /**
     * @return array
     */
    protected function failedAuthentication()
    {
        return [
            'success' => false,
            'code' => 401,
            'message' => 'Attempt failed.',
            'data' => []
        ];
    }
}
