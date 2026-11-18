<?php

namespace Tests\Unit;

use App\Http\Requests\StoreLdapDirectoryRequest;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class LdapDirectorySearchBaseValidationTest extends TestCase
{
    /** @dataProvider configurations */
    public function test_search_bases_are_validated_on_the_server(array $input, array $errors): void
    {
        $request = StoreLdapDirectoryRequest::create('/', 'POST', $input);
        (new ReflectionMethod($request, 'prepareForValidation'))->invoke($request);
        $rules = array_intersect_key($request->rules(), array_flip(['base_dn', 'user_dn', 'group_dn']));
        $validator = Validator::make($request->all(), $rules, $request->messages());

        $this->assertSame($errors, $validator->errors()->keys());
        if ($errors === [] && empty($input['base_dn'])) {
            // The existing database column is NOT NULL; empty must persist as ''.
            $this->assertSame('', $validator->validated()['base_dn']);
        }
    }

    public static function configurations(): array
    {
        return [
            'existing base only' => [['base_dn' => 'dc=example,dc=com'], []],
            'existing relative branches' => [['base_dn' => 'dc=example,dc=com', 'user_dn' => 'ou=People', 'group_dn' => 'ou=Groups'], []],
            'explicit empty base' => [['base_dn' => '', 'user_dn' => 'ou=users,dc=cloudron', 'group_dn' => 'ou=groups,dc=cloudron'], []],
            'middleware converted empty base to null' => [['base_dn' => null, 'user_dn' => 'ou=users,dc=cloudron', 'group_dn' => 'ou=groups,dc=cloudron'], []],
            'omitted base' => [['user_dn' => 'ou=People,o=Example', 'group_dn' => 'ou=Groups,o=Other'], []],
            'missing both paths' => [[], ['user_dn', 'group_dn']],
            'missing user path' => [['group_dn' => 'ou=groups,dc=cloudron'], ['user_dn']],
            'missing group path' => [['user_dn' => 'ou=users,dc=cloudron'], ['group_dn']],
            'blank paths' => [['user_dn' => ' ', 'group_dn' => ''], ['user_dn', 'group_dn']],
            'invalid base type' => [['base_dn' => ['dc=example']], ['base_dn']],
        ];
    }
}
