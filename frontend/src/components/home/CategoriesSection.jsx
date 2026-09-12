import { useState, useEffect } from 'react';
import { categoryService } from '../../services/categoryService';
import SectionHeader from '../common/SectionHeader';
import CategoryScroll from '../category/CategoryScroll';

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
          <div className="category-scroll-wrap">
            <div className="category-scroll">
              {Array.from({ length: 6 }, (_, i) => (
                <div key={i} className="category-scroll-item">
                  <span className="category-scroll-icon skeleton-shimmer" />
                  <span className="skeleton-line skeleton-shimmer w-75" />
                </div>
              ))}
            </div>
          </div>
        ) : (
          <CategoryScroll categories={categories} />
        )}
      </div>
    </section>
  );
}
