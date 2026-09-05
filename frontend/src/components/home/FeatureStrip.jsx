import { TruckIcon, ShieldIcon, LeafIcon, TagIcon } from '../../assets/icons';

const FEATURES = [
  { icon: LeafIcon, title: 'Organic Farm Source', desc: '100% certified organic produce' },
  { icon: TruckIcon, title: 'Free Home Delivery', desc: 'On orders over $50' },
  { icon: ShieldIcon, title: 'Quality Guaranteed', desc: 'Freshness you can trust' },
  { icon: TagIcon, title: 'Weekly Promotions', desc: 'Save big every week' },
];

export default function FeatureStrip() {
  return (
    <section className="feature-strip">
      <div className="container-lg">
        <div className="feature-strip-grid">
          {FEATURES.map((f) => (
            <div key={f.title} className="feature-strip-item">
              <div className="feature-strip-icon">
                <f.icon size={28} />
              </div>
              <div>
                <h3 className="feature-strip-title">{f.title}</h3>
                <p className="feature-strip-desc">{f.desc}</p>
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
