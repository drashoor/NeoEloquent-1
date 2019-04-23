<?php namespace Vinelab\NeoEloquent\Tests\Functional\Relations\HasOne;

use Mockery as M;
use Vinelab\NeoEloquent\Tests\TestCase;
use Vinelab\NeoEloquent\Eloquent\Model;

class User extends Model
{

    protected $label = 'Individual';
    protected $fillable = ['name', 'email'];

    public function profile()
    {
        return $this->hasOne('Vinelab\NeoEloquent\Tests\Functional\Relations\HasOne\Profile', 'PROFILE');
    }
}

class CreateUpdateSaveTest extends TestCase
{

    public function tearDown()
    {
        M::close();

        $users = User::all();
        $users->each(function ($u) {
            $u->delete();
        });

        parent::tearDown();
    }

    public function setUp()
    {
        parent::setUp();

        $resolver = M::mock('Illuminate\Database\ConnectionResolverInterface');
        $resolver->shouldReceive('connection')->andReturn($this->getConnectionWithConfig('default'));

        User::setConnectionResolver($resolver);
    }

    public function testDynamicLoadingHasOneFromFoundRecord()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);
        $user->update(['name' => 'x']);
        $this->assertEquals('x', $user->fresh()->name);
        $user->email = 'y';
        $user->save();
        $this->assertEquals('y', $user->fresh()->email);

        $user->email = 'z';
        $user->push();
        $this->assertEquals('z', $user->fresh()->email);
    }
}
