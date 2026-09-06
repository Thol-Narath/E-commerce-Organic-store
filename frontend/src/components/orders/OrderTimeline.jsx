import { CheckIcon } from '../../assets/icons';
import { getOrderFlowIndex } from './OrderStatusBadge';

const STEPS = [
  { key: 'placed', label: 'Order Placed' },
  { key: 'confirmed', label: 'Confirmed' },
  { key: 'processing', label: 'Processing' },
  { key: 'packed', label: 'Packed' },
  { key: 'shipped', label: 'Shipped' },
  { key: 'out_for_delivery', label: 'Out for Delivery' },
  { key: 'delivered', label: 'Delivered' },
];

/**
 * Visual vertical order tracking timeline.
 *
 * - Completed steps: green check circle
 * - Current step: highlighted green, pulsing indicator
 * - Future steps: grey
 * - Cancelled order: red cancelled state across the whole track
 */
export default function OrderTimeline({ status }) {
  const currentIndex = getOrderFlowIndex(status);
  const cancelled = status === 'cancelled' || status === 'refunded';

  return (
    <div className="order-timeline">
      {cancelled ? (
        <div className="order-timeline-cancelled">
          <div className="order-timeline-cancelled-ring">
            <span className="order-timeline-cancelled-x">×</span>
          </div>
          <div>
            <div className="fw-semibold text-danger">
              {status === 'refunded' ? 'Order Refunded' : 'Order Cancelled'}
            </div>
            <div className="text-muted small">
              This order is no longer active and will not be fulfilled.
            </div>
          </div>
        </div>
      ) : (
        <ol className="order-timeline-list">
          {STEPS.map((step, index) => {
            const done = index < currentIndex || status === 'delivered';
            const current = index === currentIndex && status !== 'delivered';

            return (
              <li
                key={step.key}
                className={`order-timeline-step ${
                  done ? 'done' : current ? 'current' : 'upcoming'
                }`}
              >
                <div className="order-timeline-dot">
                  {done ? (
                    <CheckIcon size={14} className="order-timeline-check" />
                  ) : current ? (
                    <span className="order-timeline-pulse" />
                  ) : null}
                </div>
                <div className="order-timeline-content">
                  <span className="order-timeline-label">{step.label}</span>
                  {current && <span className="order-timeline-current-badge">Current</span>}
                </div>
              </li>
            );
          })}
        </ol>
      )}
    </div>
  );
}
