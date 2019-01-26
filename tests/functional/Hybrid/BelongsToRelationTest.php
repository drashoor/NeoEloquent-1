<?php namespace Vinelab\NeoEloquent\Tests\Functional\Relations\Hybrid;

use Illuminate\Database\Schema\Blueprint;
use Mockery as M;
use Vinelab\NeoEloquent\Eloquent\Relations\Hybrid\HybridRelations;
use Vinelab\NeoEloquent\Tests\TestCase;

class Invitation extends \Illuminate\Database\Eloquent\Model
{
    use HybridRelations;

    protected $connection = "sqlite";
    protected $fillable = ['name', 'mobile', 'member_id'];

    public function member()
    {
        return $this->belongsToHybrid(Member::class, 'member_id');
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
        Invitation::setConnectionResolver($this->resolver);
        Member::setConnectionResolver($this->resolver);

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
        $this->markTestSkipped();
        $member = Member::create(["name" => "yassir"]);
        $invitation = Invitation::create(['name' => 'Daughter', 'mobile' => '0565656', "member_id" => (int)$member->id]);
        $invitations = Invitation::with("member")->get();
        $this->assertNotNull($invitations->first()->member);
        $this->assertNotNull($invitation->member);
        $this->assertEquals($member, $invitation->member);
        $this->assertEquals($invitation, $member->inviation);
    }

    public function testEagerLoadingBelongsTo()
    {
    }
}
