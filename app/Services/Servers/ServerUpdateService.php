<?php

namespace App\Services\Servers;

use App\Models\Objects\Server\ServerSpecificationsObject;
use App\Models\Server;
use App\Repositories\Proxmox\Server\ProxmoxAllocationRepository;
use App\Repositories\Proxmox\Server\ProxmoxPowerRepository;
use App\Services\ProxmoxService;
use Illuminate\Support\Arr;
use Spatie\LaravelData\Optional;
use Webmozart\Assert\Assert;

class ServerUpdateService extends ProxmoxService
{
    private AllocationService $allocationService;
    private CloudinitService $cloudinitService;
    private NetworkService $networkService;
    private ServerDetailService $detailService;
    private ProxmoxPowerRepository $powerRepository;
    private ProxmoxAllocationRepository $allocationRepository;

    public function __construct()
    {
        $this->allocationService = new AllocationService;
        $this->cloudinitService = new CloudinitService;
        $this->networkService = new NetworkService;
        $this->detailService = new ServerDetailService;
        $this->powerRepository = new ProxmoxPowerRepository;
        $this->allocationRepository = new ProxmoxAllocationRepository;
    }


    public function handle(ServerSpecificationsObject $deployment)
    {
        Assert::isInstanceOf($this->server, Server::class);
        $this->allocationService->setServer($this->server);
        $this->networkService->setServer($this->server);
        $this->cloudinitService->setServer($this->server);
        $this->detailService->setServer($this->server);
        $this->allocationRepository->setServer($this->server);
        $this->powerRepository->setServer($this->server);

        /* 2. Configure the specifications */
        if ($deployment->limits->cpu || $deployment->limits->memory)
            $this->allocationService->updateSpecifications([
                'cpu' => $deployment->limits->cpu,
                'memory' => $deployment->limits->memory,
            ]);

        /* 3. Configure the IPs */
        if ($deployment->limits->addresses?->ipv4 || $deployment->limits->addresses?->ipv6)
        {
            $this->networkService->clearIpsets();

            $this->cloudinitService->updateIpConfig($deployment->limits->addresses->toArray());
            $this->networkService->lockIps(Arr::flatten($this->server->addresses()->get(['address'])->toArray()));
        }

        if ($deployment->limits->addresses->ipv4->mac_address) {
            $this->networkService->updateMacAddress($deployment->limits->addresses->ipv4->mac_address);
        }

        /* 4. Configure the disks */
        $templateDetails = $this->detailService->getDetails();

        // Assume the first entry in the boot disks will be the one to resize. All other disks will be dynamically resized/recreated, but this behavior guarantees that a hosting provider can set the disk size no matter the disk type
        $primaryDisk = collect($deployment->configuration->disks)->where('disk', Arr::first($deployment->configuration->boot_order))->first();
        $templatePrimaryDisk = collect($templateDetails->configuration->disks)->where('disk', Arr::first($templateDetails->configuration->boot_order))->first();

        if ($primaryDisk !== null && $templatePrimaryDisk !== null) {
            // If there's no primary disk, then we don't have to do any resizing. Easy!
            $diff = $this->allocationService->convertToBytes($primaryDisk['size']) - $this->allocationService->convertToBytes($templatePrimaryDisk['size']);

            if ($diff > 0)
                $this->allocationRepository->resizeDisk($diff, $templatePrimaryDisk['disk']);
        }

        /* 5. Kill the server to guarantee configurations are active */
        $this->powerRepository->send('stop');

        return $this->detailService->getDetails();
    }
}
