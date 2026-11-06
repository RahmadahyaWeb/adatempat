import {
    Car,
    Check,
    DoorOpen,
    Moon,
    Projector,
    Snowflake,
    Trophy,
    Utensils,
    Wifi,
    type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import type { Amenity } from '@/types/catalog';

const amenityIcons: Record<string, LucideIcon> = {
    wifi: Wifi,
    car: Car,
    'door-open': DoorOpen,
    moon: Moon,
    utensils: Utensils,
    snowflake: Snowflake,
    scoreboard: Trophy,
    projector: Projector,
};

export function AmenityList({
    amenities,
    className,
}: {
    amenities: Amenity[];
    className?: string;
}) {
    return (
        <ul
            className={cn(
                'grid gap-x-6 gap-y-3 font-body text-sm text-ink sm:grid-cols-2',
                className,
            )}
        >
            {amenities.map((amenity) => {
                const Icon = amenityIcons[amenity.icon] ?? Check;

                return (
                    <li
                        key={amenity.id}
                        className="flex min-w-0 items-center gap-2.5"
                    >
                        <Icon
                            aria-hidden="true"
                            strokeWidth={1.5}
                            className="size-4 shrink-0 text-stone"
                        />
                        <span className="min-w-0 break-words">
                            {amenity.name}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}
