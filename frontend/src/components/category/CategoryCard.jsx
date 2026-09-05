import { Link } from 'react-router-dom';

/**
 * Category card linking to the category product page.
 *
 * Photo-first vertical layout: real food photo in a circular frame → name →
 * description → product count. "Coming soon" categories are muted.
 */
export default function CategoryCard({ category }) {
  const count = Number(category.products_count) || 0;
  const isComingSoon = count === 0;
  const iconSrc = category.icon_url || '/category-icons/category.svg';

  return (
    <Link
      to={`/categories/${category.slug}`}
      className={`category-card-modern h-100 d-flex flex-column align-items-center text-center text-decoration-none p-4${isComingSoon ? ' category-card-coming-soon' : ''}`}
    >
      <div className="category-icon-circle">
        <img
          src={iconSrc}
          alt={category.name}
          className="category-icon-circle-img"
          loading="lazy"
        />
      </div>

      <span className="category-card-title">{category.name}</span>

      {category.description && (
        <span className="category-card-desc">{category.description}</span>
      )}

      <span className={`category-badge ${isComingSoon ? 'category-badge-muted' : 'category-badge-active'}`}>
        {isComingSoon ? 'Coming soon' : `${count} product${count > 1 ? 's' : ''}`}
      </span>
    </Link>
  );
}