import { useState, useEffect } from 'react';
import { testimonialService } from '../../services/testimonialService';
import SectionHeader from '../common/SectionHeader';
import { StarIcon } from '../../assets/icons';

export default function TestimonialsSection() {
  const [testimonials, setTestimonials] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    testimonialService.getTestimonials().then((data) => {
      setTestimonials(data || []);
    }).catch(() => {}).finally(() => setLoading(false));
  }, []);

  if (!loading && testimonials.length === 0) return null;

  return (
    <section className="section-testimonials py-5">
      <div className="container-lg">
        <SectionHeader
          title="Why Customers Love Us"
          subtitle="Hear what our customers have to say about us"
        />

        {loading ? (
          <div className="testimonials-grid">
            {Array.from({ length: 3 }, (_, i) => (
              <div key={i} className="testimonial-card skeleton-shimmer" style={{ height: 200 }} />
            ))}
          </div>
        ) : (
          <div className="testimonials-grid">
            {testimonials.map((t) => (
              <div key={t.id} className="testimonial-card">
                <div className="testimonial-stars">
                  {Array.from({ length: 5 }, (_, i) => (
                    <StarIcon
                      key={i}
                      size={16}
                      fill={i < t.rating ? '#f59e0b' : 'none'}
                      className={i < t.rating ? 'star-filled' : 'star-empty'}
                    />
                  ))}
                </div>
                <p className="testimonial-quote">&ldquo;{t.quote}&rdquo;</p>
                <div className="testimonial-author">
                  <div className="testimonial-avatar">
                    {t.avatar_url ? (
                      <img src={t.avatar_url} alt={t.name} />
                    ) : (
                      <span>{t.name?.charAt(0) || '?'}</span>
                    )}
                  </div>
                  <div>
                    <span className="testimonial-name">{t.name}</span>
                    {t.role && <span className="testimonial-role">{t.role}</span>}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </section>
  );
}
