<?php

namespace App\Http\Controllers\Application\Servers;

use App\Http\Controllers\ApplicationApiController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Application\Servers\StoreServerRequest;
use App\Http\Requests\Application\Servers\UpdateServerRequest;
use App\Http\Requests\Application\Servers\UpdateSpecificationsRequest;
use App\Models\Server;
use App\Services\Servers\CreationService;
use App\Services\Servers\InstallService;
use App\Services\Servers\NetworkService;
use App\Services\Servers\ResourceService;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class ServerController extends ApplicationApiController
{
    public function __construct(private CreationService $creationService, private ResourceService $resourceService, private NetworkService $networkService, private InstallService $installService)
    {

    }

    public function index(Request $request)
    {
        $servers = QueryBuilder::for(Server::query())
            ->allowedFilters(['user_id', 'node_id', 'vmid', 'name', 'description', 'installing'])
            ->allowedSorts(['id', 'user_id', 'node_id', 'vmid'])
            ->paginate($request->query('per_page') ?? 50);

        return $servers;
    }

    public function store(StoreServerRequest $request)
    {
        $server = $this->creationService->handle($request->type, $request->validated(), $request->is_template, $request->is_visible);

        return $this->returnContent([
            'data' => $server,
            'message' => 'Created server'
        ]);
    }

    public function show(Server $server)
    {
        return $this->returnContent([
            'data' => $server
        ]);
    }

    public function destroy(Server $server, Request $request)
    {
        if ($request->purge === true) {
            $this->installService->setServer($server)->delete();
        }

        $server->delete();

        return $this->returnContent([
            'message' => 'Deleted server'
        ]);
    }

    public function update(Server $server, UpdateServerRequest $request)
    {
        $server = $server->update($request->validated());

        return $this->returnContent([
            'data' => $server,
            'message' => 'Updated server'
        ]);
    }

    public function getSpecifications(Server $server)
    {
        return $this->returnContent([
            'data' => $this->resourceService->setServer($server)->getSpecifications()
        ]);
    }

    public function updateSpecifications(Server $server, UpdateSpecificationsRequest $request)
    {
        $this->resourceService->setServer($server);

        if ($request->cores) {
            $this->resourceService->setCores($request->cores);
        }

        if ($request->memory) {
            $this->resourceService->setMemory($request->memory);
        }

        if ($request->disks) {
            $existingDisks = $this->resourceService->getDisks();
            $this->resourceService->updateDisks($request->disks, $existingDisks);
        }

        if ($request->lockIps) {
            $this->networkService->lockIps($request->lockIps);
        }

        return $this->returnContent([
            'message' => 'Updated specifications'
        ]);
    }
}
