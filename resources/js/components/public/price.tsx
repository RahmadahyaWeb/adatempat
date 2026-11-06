import { formatRupiah } from '@/lib/catalog';
import { cn } from '@/lib/utils';

export function Price({
    amount,
    className,
}: {
    amount: number;
    className?: string;
}) {
    return (
        <span className={cn('font-data text-sm tabular-nums', className)}>
            {formatRupiah(amount)}
        </span>
    );
}
