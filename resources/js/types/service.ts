export type DepositType = 'none' | 'fixed' | 'percent';

export type Service = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    buffer_after_minutes: number;
    /** Minor units. */
    price: number;
    deposit_type: DepositType;
    /** Minor units for a fixed deposit, 1 to 100 for a percentage. */
    deposit_value: number;
    is_active: boolean;
    /** Minor units, worked out by the server. Only on the list page. */
    deposit?: number;
    /** How many people offer it. Only on the list page. */
    staff_count?: number;
    /** Who offers it. Only on the edit page. */
    staff_ids?: number[];
};
