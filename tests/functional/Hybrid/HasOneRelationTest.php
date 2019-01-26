<?php namespace Vinelab\NeoEloquent\Tests\Functional\Relations\Hybrid;

use Illuminate\Database\Schema\Blueprint;
use Mockery as M;
use Vinelab\NeoEloquent\Eloquent\Relations\Hybrid\HybridRelations;
use Vinelab\NeoEloquent\Tests\TestCase;
use \Illuminate\Database\DatabaseManager;
use \Illuminate\Database\Connectors\ConnectionFactory;
use \Illuminate\Database\Schema\Builder as SchemaBuilder;

class User extends \Illuminate\Database\Eloquent\Model
{
    use HybridRelations;

    protected $connection = "sqlite";
    protected $fillable = ['name', 'email'];

    public function profile()
    {
        return $this->hasOneHybrid(Profile::class, 'user_id');
    }
}

class Profile extends \Vinelab\NeoEloquent\Eloquent\Model
{
    use HybridRelations;

    protected $label = 'Profile';
    protected $connection = "neo4j";
    protected $fillable = ['guid', 'service', 'user_id'];

    public function user()
    {
        return $this->belongsToHybrid(User::class, 'user_id');
    }
}

class HasOneHybridRelationTest extends TestCase
{
    protected $db;

    protected $schema;

    protected function prepareDatabase()
    {
        $config = [
            'database.default' => 'neo4j',
            'database.connections' => $this->dbConfig['connections']
        ];
        $container = m::mock('Illuminate\Container\Container');
        $container->shouldReceive('bound')->andReturn(false);
        $container->shouldReceive('offsetGet')->with('config')->andReturn($config);
        $db = new DatabaseManager(
            $container,
            new ConnectionFactory($container)
        );
        $this->db = $db;
        $sqliteConnection = $this->db->connection('sqlite');
        $sqliteConnection->setSchemaGrammar(new \Illuminate\Database\Schema\Grammars\SQLiteGrammar);
        $sqliteConnection->setQueryGrammar(new \Illuminate\Database\Query\Grammars\SQLiteGrammar);
        $this->schema = new SchemaBuilder($sqliteConnection);

        $resolver = M::mock('Illuminate\Database\ConnectionResolverInterface');
        $resolver->shouldReceive('connection')
            ->withArgs(["sqlite"])
            ->andReturn($sqliteConnection);

        $neo4jConnection = $this->getConnectionWithConfig('neo4j');

        $resolver->shouldReceive('connection')
            ->withArgs(["neo4j"])
            ->andReturn($neo4jConnection);

        $resolver->shouldReceive('connection')
            ->withArgs([null])
            ->andReturn($neo4jConnection);

        $resolver->shouldReceive('getDefaultConnection')
            ->andReturn($this->getConnectionWithConfig('default'));

        User::setConnectionResolver($resolver);
        Profile::setConnectionResolver($resolver);
    }

    public function tearDown()
    {
        M::close();
        $this->schema->dropIfExists('users');
        Profile::where("id", ">", -1)->delete();

        parent::tearDown();
    }

    public function setUp()
    {
        parent::setUp();
        $this->prepareDatabase();

        $this->schema->create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('email');
            $t->timestamps();
        });
    }

    public function testDynamicLoadingHasOne()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);
        $profile = Profile::create(['guid' => uniqid(), 'service' => 'twitter', 'user_id' => $user->id]);

        $this->assertNotNull($user->profile);
        $this->assertEquals($profile->toArray(), $user->profile->toArray());
    }

    public function testDynamicLoadingHasOneFromFoundRecord()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);
        $profile = Profile::create(['guid' => uniqid(), 'service' => 'twitter', 'user_id' => $user->id]);

        $found = User::find($user->id);

        $this->assertEquals($profile->toArray(), $found->profile->toArray());
    }

    public function testEagerLoadingHasOne()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);
        $profile = Profile::create(['guid' => uniqid(), 'service' => 'twitter', 'user_id' => $user->id]);

        $found = User::with('profile')->find($user->id);
        $relations = $found->getRelations();

        $this->assertArrayHasKey('profile', $relations);
        $this->assertEquals($profile->toArray(), $relations['profile']->toArray());
    }

    public function testCreateRelatedHasOneModel()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);

        $profile = $user->profile()->create(['guid' => uniqid(), 'service' => 'twitter']);

        $this->assertEquals($user->profile->toArray(), $profile->toArray());
        $saved = User::find($user->id);
        $this->assertEquals($profile->toArray(), $saved->profile->toArray());
    }


    public function testUpdateRelatedHasOneModel()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);
        $profile = $user->profile()->create(['guid' => uniqid(), 'service' => 'twitter', 'user_id' => $user->id]);

        $user->profile()->update(["service" => "fb"]);

        $this->assertNotEquals($user->profile->toArray(), $profile->toArray());
        $this->assertEquals($user->profile->toArray(), $profile->fresh()->toArray());
        $saved = User::find($user->id);
        $this->assertEquals($profile->fresh()->toArray(), $saved->profile->toArray());
    }

    public function testUpdatingModelWithRelatedModel()
    {
        $user = User::create(['name' => 'Tests', 'email' => 'B']);
        Profile::create(['guid' => uniqid(), 'service' => 'twitter', 'user_id' => $user->id]);

        $user->name = "test_name";
        $user->profile->service = "facebook";
        $user->push();

        $user = $user->fresh();

        $this->assertEquals("test_name", $user->name);
        $this->assertEquals("facebook", $user->profile->service);
    }
}
