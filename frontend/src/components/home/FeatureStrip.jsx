import { TruckIcon, ShieldIcon, LeafIcon, LockIcon } from '../../assets/icons';

const FEATURES = [
  { icon: LeafIcon, title: 'Fresh & Organic', desc: 'Carefully selected products' },
  { icon: TruckIcon, title: 'Fast Delivery', desc: 'Fresh groceries delivered quickly' },
  { icon: LockIcon, title: 'Secure Payment', desc: 'Safe and convenient checkout' },
  { icon: ShieldIcon, title: 'Quality Guarantee', desc: 'We care about product quality' },
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
