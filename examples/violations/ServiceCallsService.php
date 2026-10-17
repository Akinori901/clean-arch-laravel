<?php
// 違反例 3: Service が別の Service を呼ぶ（Service 間の調整は UseCase の仕事）
// 期待: tools/no-service-to-service.php が検知する（deptrac は同じレイヤー内の依存を見ない）

namespace App\Services;

final class ViolatingOrderService
{
    public function __construct(private UserService $users)
    {
    }

    public function ownerName(int $userId): string
    {
        return $this->users->findActive($userId)->name;
    }
}
