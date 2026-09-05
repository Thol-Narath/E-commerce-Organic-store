import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { categoryService } from '../../services/categoryService';
import SectionHeader from '../common/SectionHeader';
import ImageWithFallback from '../common/ImageWithFallback';

export default function CategoriesSection() {
  const [categories, setCategories] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    let cancelled = false;
    categoryService.getCategories().then((data) => {
      if (!cancelled) setCategories(data || []);
    }).catch(() => {}).finally(() => {
      if (!cancelled) setLoading(false);
    });
    return () => { cancelled = true; };
  }, []);

  if (!loading && categories.length === 0) return null;

  return (
    <section className="section-categories-home py-5">
      <div className="container-lg">
        <SectionHeader
          title="Browse Our Hottest Categories"
          subtitle="Explore our wide range of organic products"
          link="/categories"
          linkText="See All"
        />

        {loading ? (
          <div className="categories-grid-home">
            {Array.from({ length: 6 }, (_, i) => (
              <div key={i} className="category-home-card skeleton-shimmer" />
            ))}
          </div>
        ) : (
          <div className="categories-grid-home">
            {categories.map((cat) => (
              <Link
                key={cat.id}
                to={`/categories/${cat.slug}`}
                className="category-home-card text-decoration-none"
              >
                <div className="category-home-icon">
                  <ImageWithFallback
                    src={cat.icon_url || '/category-icons/category.svg'}
                    alt={cat.name}
                    className="category-home-icon-img"
                  />
                </div>
                <span className="category-home-name">{cat.name}</span>
                <span className="category-home-count">
                  {cat.products_count || 0} Items
                </span>
              </Link>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
