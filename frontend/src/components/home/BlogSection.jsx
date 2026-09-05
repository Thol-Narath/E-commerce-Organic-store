import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { blogService } from '../../services/blogService';
import SectionHeader from '../common/SectionHeader';
import ImageWithFallback from '../common/ImageWithFallback';

export default function BlogSection() {
  const [blogs, setBlogs] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    blogService.getBlogs({ per_page: 3 }).then((data) => {
      setBlogs(data?.items || data || []);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  if (!loading && blogs.length === 0) return null;

  return (
    <section className="section-blog py-5 bg-light">
      <div className="container-lg">
        <SectionHeader
          title="Latest News & Blogs"
          subtitle="Stay updated with our latest articles"
          link="/blog"
          linkText="View All"
        />

        {loading ? (
          <div className="row g-4">
            {Array.from({ length: 3 }, (_, i) => (
              <div key={i} className="col-md-4">
                <div className="blog-card skeleton-shimmer" style={{ height: 320 }} />
              </div>
            ))}
          </div>
        ) : (
          <div className="row g-4">
            {blogs.map((blog) => (
              <div key={blog.id} className="col-md-4">
                <Link to={`/blog/${blog.slug}`} className="blog-card text-decoration-none">
                  <div className="blog-card-image">
                    <ImageWithFallback
                      src={blog.image_url}
                      alt={blog.title}
                      className="w-100 h-100"
                    />
                  </div>
                  <div className="blog-card-body">
                    <div className="blog-card-meta">
                      {blog.category && <span className="blog-card-category">{blog.category}</span>}
                      {blog.published_at && (
                        <span className="blog-card-date">
                          {new Date(blog.published_at).toLocaleDateString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                          })}
                        </span>
                      )}
                    </div>
                    <h3 className="blog-card-title">{blog.title}</h3>
                    <p className="blog-card-excerpt">{blog.excerpt}</p>
                  </div>
                </Link>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
