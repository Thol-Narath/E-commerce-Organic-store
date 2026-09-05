import { useState, useEffect } from 'react';
import { statsService } from '../../services/statsService';

export default function StatsBar() {
  const [stats, setStats] = useState(null);

  useEffect(() => {
    statsService.getStats().then(setStats).catch(() => {});
  }, []);

  const allStats = [
    { label: 'Happy Clients', value: '2,500+' },
    { label: 'Active Products', value: stats?.active_products?.toLocaleString() || '...' },
    { label: 'Categories', value: stats?.active_categories?.toString() || '...' },
    { label: 'Our Awards', value: '15+' },
  ];

  return (
    <section className="stats-bar">
      <div className="container-lg">
        <div className="stats-bar-grid">
          {allStats.map((stat) => (
            <div key={stat.label} className="stats-bar-item">
              <span className="stats-bar-value">{stat.value}</span>
              <span className="stats-bar-label">{stat.label}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
