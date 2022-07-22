<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use App\Models\Domain;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Providers\RouteServiceProvider;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;
    protected $redirectAfterLogout = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }


    /**
     * Handle a login request to the application.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(Request $request)
    {
        //Support for legacy authentication using domain user
        // Find the user in DB with submitted values and replace the username with the request 
        //if (str_contains($request->user_email,"nemerald.net")) {
            $userid = explode("@",$request->user_email);
            $domain = Domain::where('domain_name', $userid[1])->first();

            if (isset($domain)){
                $params = [
                    'domain_uuid' => $domain->domain_uuid, 
                    'username' => $userid[0],
                ];
                $user= User::where('domain_uuid', $domain->domain_uuid)
                    ->where ('username', 'ilike', '%' . $userid[0] . '%')
                    ->first();

                if(isset($user)){
                    $request->merge([
                        'user_email' => $user->user_email,
                    ]);
                }

            }

        //}
 
        $this->validateLogin($request);

        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if ($this->attemptLogin($request)) {
            if ($request->hasSession()) {
                $request->session()->put('auth.password_confirmed_at', time());
            }

            return $this->sendLoginResponse($request);
        }

        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function authenticated(Request $request, $user)
    {
        $user= User::where('user_email', $request->user_email)->first();
        $domain = Domain::where('domain_uuid', $user->domain_uuid)->first();

        //if (session_status()!==PHP_SESSION_ACTIVE){
            //session_start();
            $_SESSION['LARAVEL_UN'] = $user->username;// . "@" . $domain->domain_name;
            $_SESSION['LARAVEL_PW'] = $request->password;
            $_SESSION['user']['domain_name'] = Session::get('user.domain_name');
            $_SESSION['user']['domain_uuid'] = Session::get('user.domain_uuid');
        //}

        return redirect('/core/dashboard');

    }

    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'user_email';
    }

    /**
     * Logout, Clear Session, and Return.
     *
     * @return void
     */
    public function logout()
    {
        // $user = Auth::user();
        // Log::info('User Logged Out. ', [$user]);
        session_start();
        session_unset();
        session_destroy();

        Auth::logout();
        Session::flush();

        return redirect(property_exists($this, 'redirectAfterLogout') ? $this->redirectAfterLogout : '/');
    }
}
