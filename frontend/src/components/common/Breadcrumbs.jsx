import { Link } from 'react-router-dom';
import { ChevronRightIcon } from '../../assets/icons';

/**
 * Lightweight breadcrumb trail. `items` is an array of { label, to? }.
 * The last item (without `to`) is rendered as the current (unlinked) crumb.
 */
export default function Breadcrumbs({ items }) {
  if (!items || items.length === 0) return null;

  return (
    <nav aria-label="Breadcrumb" className="account-breadcrumbs">
      <ol className="account-breadcrumbs-list">
        {items.map((item, index) => {
          const isLast = index === items.length - 1;
          return (
            <li key={index} className="account-breadcrumb-item">
              {item.to && !isLast ? (
                <Link to={item.to} className="account-breadcrumb-link">
                  {item.label}
                </Link>
              ) : (
                <span className="account-breadcrumb-current" aria-current="page">
                  {item.label}
                </span>
              )}
              {!isLast && <ChevronRightIcon size={14} className="account-breadcrumb-sep" />}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
