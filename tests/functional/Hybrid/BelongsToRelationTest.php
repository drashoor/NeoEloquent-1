<?php namespace Vinelab\NeoEloquent\Tests\Functional\Relations\Hybrid;

use Illuminate\Database\Schema\Blueprint;
use Mockery as M;
use Vinelab\NeoEloquent\Eloquent\Relations\Hybrid\HybridRelations;
use Vinelab\NeoEloquent\Tests\TestCase;
use \Illuminate\Database\DatabaseManager;
use \Illuminate\Database\Connectors\ConnectionFactory;
use \Illuminate\Database\Schema\Builder as SchemaBuilder;

class Invitation extends \Illuminate\Database\Eloquent\Model
{
    use HybridRelations;

    protected $connection = "sqlite";
    protected $fillable = ['name', 'mobile', 'member_id'];

    public function member()
    {
        return $this->belongsToHybrid(Member::class, 'member_id');
    }

    public function getAttribute($key)
    {
    }
}

class Member extends \Vinelab\NeoEloquent\Eloquent\Model
{
    use HybridRelations;

    protected $label = 'member';
    protected $table = 'member';
    protected $connection = "neo4j";
    protected $fillable = ['name'];

    public function invitation()
    {
        return $this->hasOneHybrid(Invitation::class, 'member_id');
    }
}

class BelongsToRelationTest extends TestCase
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

        Invitation::setConnectionResolver($resolver);
        Member::setConnectionResolver($resolver);
    }

    public function tearDown()
    {
        M::close();
        $this->schema->dropIfExists('invitations');
        Member::where("id", ">", -1)->delete();

        parent::tearDown();
    }

    public function setUp()
    {
        parent::setUp();
        $this->prepareDatabase();

        $this->schema->create('invitations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('mobile');
            $t->integer('member_id');
            $t->timestamps();
        });
    }

    public function testDynamicLoadingBelongsTo()
    {
        $member = Member::create(["name" => "yassir"]);
        $invitation = Invitation::create(['name' => 'Daughter', 'mobile' => '0565656', "member_id" => $member->id]);
        $invitations = Invitation::with(["member"])->get();
        $this->assertNotNull($invitations->first()->member);
        $this->assertNotNull($invitation->member);
        $this->assertEquals($member, $invitation->member);
        $this->assertEquals($invitation, $member->inviation);
    }

    public function testDynamicLoadingBelongsToFromFoundRecord()
    {
        $location = Member::create(['lat' => 89765, 'long' => -876521234, 'country' => 'The Netherlands', 'city' => 'Amsterdam']);
        $user = Invitation::create(['name' => 'Daughter', 'alias' => 'daughter']);
        $relation = $location->user()->associate($user);

        $this->assertTrue($relation->save());

        $found = Member::find($location->id);

        $this->assertEquals($user->toArray(), $found->user->toArray());
        $this->assertTrue($relation->delete());
    }

    public function testEagerLoadingBelongsTo()
    {
        $location = Member::create(['lat' => 89765, 'long' => -876521234, 'country' => 'The Netherlands', 'city' => 'Amsterdam']);
        $user = Invitation::create(['name' => 'Daughter', 'alias' => 'daughter']);
        $relation = $location->user()->associate($user);

        $this->assertTrue($relation->save());

        $found = Member::with('user')->find($location->id);
        $relations = $found->getRelations();

        $this->assertArrayHasKey('user', $relations);
        $this->assertEquals($user->toArray(), $relations['user']->toArray());
        $this->assertTrue($relation->delete());
    }

    public function testAssociatingBelongingModel()
    {
        $location = Member::create(['lat' => 89765, 'long' => -876521234, 'country' => 'The Netherlands', 'city' => 'Amsterdam']);
        $user = Invitation::create(['name' => 'Daughter', 'alias' => 'daughter']);
        $relation = $location->user()->associate($user);

        $saved = $relation->save();

        $this->assertTrue($saved);
        $this->assertInstanceOf('Carbon\Carbon', $relation->created_at, 'make sure we set the created_at timestamp');
        $this->assertInstanceOf('Carbon\Carbon', $relation->updated_at, 'make sure we set the updated_at timestamp');
        $this->assertArrayHasKey('user', $location->getRelations(), 'make sure the user has been set as relation in the model');
        $this->assertArrayHasKey('user', $location->toArray(), 'make sure it is also returned when dealing with the model');
        $this->assertEquals($location->user->toArray(), $user->toArray());

        // Let's retrieve it to make sure that NeoEloquent is not lying about it.
        $saved = Member::find($location->id);
        $this->assertEquals($user->toArray(), $saved->user->toArray());

        // delete the relation and make sure it was deleted
        // so that we can delete the nodes when cleaning up.
        $this->assertTrue($relation->delete());
    }

    public function testRetrievingAssociationFromParentModel()
    {
        $location = Member::create(['lat' => 52.3735291, 'long' => 4.886257, 'country' => 'The Netherlands', 'city' => 'Amsterdam']);
        $user = Invitation::create(['name' => 'Daughter', 'alias' => 'daughter']);

        $relation = $location->user()->associate($user);
        $relation->since = 1966;
        $this->assertTrue($relation->save());

        $retrieved = $location->user()->edge($location->user);

        $this->assertInstanceOf('Vinelab\NeoEloquent\Eloquent\Edges\EdgeIn', $retrieved);
        $this->assertEquals($retrieved->since, 1966);

        $this->assertTrue($retrieved->delete());
    }

    public function testSavingMultipleAssociationsKeepsOnlyTheLastOne()
    {
        $location = Member::create(['lat' => 52.3735291, 'long' => 4.886257, 'country' => 'The Netherlands']);
        $van = Invitation::create(['name' => 'Van Gogh', 'alias' => 'vangogh']);

        $relation = $location->user()->associate($van);
        $relation->since = 1890;
        $this->assertTrue($relation->save());

        $jan = Invitation::create(['name' => 'Jan Steen', 'alias' => 'jansteen']);
        $cheating = $location->user()->associate($jan);
        $this->assertTrue($cheating->save());

        $withVan = $location->user()->edge($van);
        $this->assertNull($withVan);

        $withJan = $location->user()->edge($jan);
        $this->assertInstanceOf('Vinelab\NeoEloquent\Eloquent\Edges\EdgeIn', $withJan);
        $this->assertTrue($withJan->delete());
    }

    public function testFindingEdgeWithNoSpecifiedModel()
    {
        $location = Member::create(['lat' => 52.3735291, 'long' => 4.886257, 'country' => 'The Netherlands', 'city' => 'Amsterdam']);
        $user = Invitation::create(['name' => 'Daughter', 'alias' => 'daughter']);

        $relation = $location->user()->associate($user);
        $relation->since = 1966;
        $this->assertTrue($relation->save());

        $retrieved = $location->user()->edge();

        $this->assertInstanceOf('Vinelab\NeoEloquent\Eloquent\Edges\EdgeIn', $retrieved);
        $this->assertEquals($relation->id, $retrieved->id);
        $this->assertEquals($relation->toArray(), $retrieved->toArray());
        $this->assertTrue($relation->delete());
    }
}
