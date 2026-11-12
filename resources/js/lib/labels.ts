/** Spanish labels for the approved demo_sales catalog and the aliases generators commonly use. */
const LABELS: Record<string, string> = {
    total_amount: 'Monto',
    region: 'Región',
    sold_at: 'Fecha',
    product_name: 'Producto',
    product_id: 'ID de producto',
    quantity: 'Cantidad',
    salesperson: 'Vendedor',
    team: 'Equipo',
    total_sales: 'Ventas totales',
    total_revenue: 'Ingresos',
    orders: 'Pedidos',
    avg_ticket: 'Ticket promedio',
    units: 'Unidades',
    last_month_revenue: 'Ingresos del mes anterior',
    year_revenue: 'Ingresos del año',
    total_units: 'Unidades totales',
    units_sold: 'Unidades vendidas',
    total_quantity: 'Cantidad total',
    total_orders: 'Pedidos totales',
    order_count: 'Pedidos',
    orders_count: 'Pedidos',
    sales_count: 'Número de ventas',
    avg_sale: 'Venta promedio',
    average_sale: 'Venta promedio',
    avg_order_value: 'Valor promedio por pedido',
    average_ticket: 'Ticket promedio',
    avg_amount: 'Monto promedio',
    average_amount: 'Monto promedio',
    total_amount_sum: 'Monto total',
    revenue: 'Ingresos',
    sales: 'Ventas',
    unique_products: 'Productos distintos',
};

/** Human label for a dimension field or metric alias; unknown keys are humanised. */
export function labelFor(key: string): string {
    const known = LABELS[key];
    if (known) return known;

    const spaced = key.replace(/_/g, ' ').trim();

    return spaced.charAt(0).toUpperCase() + spaced.slice(1);
}

const OPERATORS: Record<string, string> = {
    equals: 'es',
    not_equals: 'no es',
    in: 'es uno de',
    not_in: 'no es ninguno de',
    greater_than: 'mayor que',
    greater_than_or_equal: 'mayor o igual que',
    less_than: 'menor que',
    less_than_or_equal: 'menor o igual que',
    between: 'entre',
    contains: 'contiene',
    is_null: 'está vacío',
    is_not_null: 'no está vacío',
    previous_quarter: 'trimestre anterior',
    previous_period: 'mes anterior',
};

/** Reads a structured filter as a short Spanish phrase, e.g. "Fecha: trimestre anterior". */
export function describeFilter(filter: { field: string; operator: string; value: unknown }): string {
    const field = labelFor(filter.field);
    const operator = OPERATORS[filter.operator] ?? filter.operator.replace(/_/g, ' ');

    if (filter.value === null || filter.value === undefined) {
        return filter.operator.startsWith('previous_') ? `${field}: ${operator}` : `${field} ${operator}`;
    }

    if (Array.isArray(filter.value)) {
        const values = filter.value.map(String);

        return filter.operator === 'between' ? `${field} ${operator} ${values.join(' y ')}` : `${field} ${operator} ${values.join(', ')}`;
    }

    return `${field} ${operator} ${String(filter.value)}`;
}
